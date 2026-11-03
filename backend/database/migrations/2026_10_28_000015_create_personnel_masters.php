<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        foreach (['master_data_job_titles', 'master_data_divisions'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id(); $t->string('code', 60)->unique(); $t->string('name', 100);
                $t->text('description')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
            });
        }
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('job_title_id')->nullable()->constrained('master_data_job_titles')->restrictOnDelete();
            $t->foreignId('division_id')->nullable()->constrained('master_data_divisions')->restrictOnDelete();
        });
        foreach ([['job_title','job_title_id','master_data_job_titles','JBT'], ['department','division_id','master_data_divisions','DIV']] as [$old,$foreign,$table,$prefix]) {
            $seen=[];
            foreach (DB::table('users')->orderBy('id')->get(['id',$old]) as $user) {
                $name=trim($user->$old); if ($name==='') continue;
                $key=mb_strtolower($name);
                if (!isset($seen[$key])) $seen[$key]=DB::table($table)->insertGetId(['code'=>$prefix.'-LEGACY-'.(count($seen)+1),'name'=>$name,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
                DB::table('users')->where('id',$user->id)->update([$foreign=>$seen[$key]]);
            }
        }
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) { $t->dropConstrainedForeignId('job_title_id'); $t->dropConstrainedForeignId('division_id'); });
        Schema::dropIfExists('master_data_divisions'); Schema::dropIfExists('master_data_job_titles');
    }
};