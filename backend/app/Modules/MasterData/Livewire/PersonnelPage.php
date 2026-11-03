<?php
namespace App\Modules\MasterData\Livewire;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Models\JobTitle;
use App\Modules\MasterData\Models\Division;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class PersonnelPage extends Component {
    use WithPagination;
    #[Locked] public string $section = 'employees';
    #[Locked] public ?int $editingId = null;
    #[Locked] public bool $showForm = false;
    public string $search = '';
    public string $status = '';
    public array $form = [];
    public function boot(): void { Gate::authorize('access-master-data'); }
    public function mount(string $section = 'employees'): void { abort_unless(in_array($section, ['employees','job-titles','divisions'], true), 404); $this->section = $section; }
    protected function model(): string { return match($this->section) { 'job-titles'=>JobTitle::class, 'divisions'=>Division::class, default=>User::class }; }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }
    public function openCreate(): void {
        $this->resetErrorBag(); $this->editingId=null; $this->showForm=true;
        $this->form = $this->section==='employees'
            ? ['username'=>'','employee_number'=>'','name'=>'','email'=>'','password'=>'','job_title_id'=>'','division_id'=>'','role'=>UserRole::OperatorProduksi->value,'is_active'=>true]
            : ['code'=>'','name'=>'','description'=>'','is_active'=>true];
    }
    public function openEdit(int $id): void {
        $row=($this->model())::findOrFail($id); $this->openCreate(); $this->editingId=$id;
        foreach (array_keys($this->form) as $key) { if ($key!=='password') $this->form[$key]=$row->getRawOriginal($key) ?? ''; }
        $this->form['is_active']=(bool)$row->is_active;
    }
    public function closeForm(): void { $this->showForm=false; $this->editingId=null; $this->form=[]; $this->resetErrorBag(); }
    public function save(): void {
        if (!$this->showForm) return;
        $employee=$this->section==='employees'; $table=$employee?'users':(new ($this->model()))->getTable();
        foreach (['code','username','name','email','employee_number','description'] as $key) if (isset($this->form[$key])) $this->form[$key]=trim($this->form[$key]);
        if ($employee) {
            $this->form['username']=mb_strtolower($this->form['username']);
            foreach (['email','employee_number'] as $key) if ($this->form[$key]==='') $this->form[$key]=null;
        } else $this->form['code']=mb_strtoupper($this->form['code']);
        $rules=['form.name'=>['required','string','max:'.($employee?255:100)], 'form.is_active'=>['required','boolean']];
        if ($employee) $rules += [
            'form.username'=>['required','string','max:60',Rule::unique('users','username')->ignore($this->editingId)],
            'form.employee_number'=>['nullable','string','max:40',Rule::unique('users','employee_number')->ignore($this->editingId)],
            'form.email'=>['nullable','email','max:255',Rule::unique('users','email')->ignore($this->editingId)],
            'form.password'=>[$this->editingId?'nullable':'required','string','min:8','max:255'],
            'form.role'=>['required',Rule::enum(UserRole::class)],
            'form.job_title_id'=>['required','integer',Rule::exists('master_data_job_titles','id')],
            'form.division_id'=>['required','integer',Rule::exists('master_data_divisions','id')],
        ]; else $rules += ['form.code'=>['required','string','max:60',Rule::unique($table,'code')->ignore($this->editingId)],'form.description'=>['nullable','string','max:2000']];
        $data=$this->validate($rules)['form'];
        DB::transaction(function () use ($employee,$data) {
            // Lock administrators before updating an account to preserve the last active administrator.
            if ($employee) User::where('role',UserRole::SuperAdmin->value)->orderBy('id')->lockForUpdate()->get();
            $row=$this->editingId ? ($this->model())::lockForUpdate()->findOrFail($this->editingId) : new ($this->model());
            if ($employee) {
                if ($row->id===auth()->id() && !$data['is_active']) throw ValidationException::withMessages(['form.is_active'=>'Anda tidak dapat menonaktifkan akun sendiri.']);
                if ($row->exists && $row->role===UserRole::SuperAdmin && $row->is_active && (!$data['is_active'] || $data['role']!==UserRole::SuperAdmin->value)
                    && !User::where('role',UserRole::SuperAdmin->value)->where('is_active',true)->where('id','!=',$row->id)->exists())
                    throw ValidationException::withMessages(['form.role'=>'Minimal satu IT Super Admin aktif harus dipertahankan.']);
                foreach (['job_title_id'=>[JobTitle::class,'job_title'], 'division_id'=>[Division::class,'department']] as $key=>[$model,$legacy]) {
                    $master=$model::lockForUpdate()->findOrFail($data[$key]);
                    if (!$master->is_active && (int)$row->$key!==(int)$master->id) throw ValidationException::withMessages(['form.'.$key=>'Pilih data master yang aktif.']);
                    $data[$legacy]=$master->name;
                }
                if (empty($data['password'])) unset($data['password']);
            }
            $row->fill($data)->save();
            if (!$employee) User::where($this->section==='job-titles'?'job_title_id':'division_id',$row->id)->update([$this->section==='job-titles'?'job_title':'department'=>$row->name]);
        });
        $this->closeForm(); $this->resetPage(); session()->flash('personnel-saved','Data berhasil disimpan.');
    }
    public function render() {
        $query=($this->model())::query();
        if ($this->status!=='') $query->where('is_active',$this->status==='active');
        if (trim($this->search)!=='') {
            $term='%'.mb_strtolower(trim($this->search)).'%';
            $fields=$this->section==='employees'?['name','username','employee_number','email']:['code','name'];
            $query->where(function($q) use($fields,$term) { foreach($fields as $field) $q->orWhereRaw('LOWER('.$field.') LIKE ?',[$term]); });
        }
        return view('modules.master-data.livewire.personnel-page',[
            'rows'=>$query->orderBy('name')->paginate(15),
            'title'=>match($this->section){'job-titles'=>'Jabatan','divisions'=>'Divisi',default=>'Karyawan'},
            'jobs'=>JobTitle::orderBy('name')->get(), 'divisions'=>Division::orderBy('name')->get(), 'roles'=>UserRole::cases(),
        ])->title('Master '.match($this->section){'job-titles'=>'Jabatan','divisions'=>'Divisi',default=>'Karyawan'}.' - irris.next');
    }
}