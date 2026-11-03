<form class="entry-form catalog-modal-form" wire:submit="saveProduct">
                <p class="required-fields-note">* Wajib diisi. Komposisi bahan diisi pengguna; kemurnian belum dihitung otomatis.</p>
                <div class="form-section-title"><span>01</span><div><strong>Spesifikasi produk</strong><small>Setiap produk dapat memiliki diameter, kemurnian, dan komposisi berbeda.</small></div></div>
                <div class="form-grid two">
                    <label class="field"><span class="field-label">Nama produk *</span><input type="text" wire:model="name" maxlength="255" required autofocus placeholder="Contoh: Copper Rod 8 mm - 75%" /></label>
                    <label class="field"><span class="field-label">Diameter (mm) *</span><input type="number" wire:model="diameter_mm" min="0.001" max="999999" step="0.001" required placeholder="Contoh: 8" /></label>
                    <label class="field"><span class="field-label">Kemurnian (%) *</span><input type="number" wire:model="purity_percent" min="0.0001" max="100" step="0.0001" required placeholder="Contoh: 75" /><small>Spesifikasi kemurnian produk yang ingin dibuat.</small></label>
                    <label class="field"><span class="field-label">Jumlah hasil acuan BoM (KG) *</span><input type="number" wire:model="bom_output_kg" min="0.001" max="999999999" step="0.001" required placeholder="Contoh: 1000" /><small>Contoh: isi 1000 untuk mencatat bahan bagi 1.000 KG hasil Copper Rod.</small></label>
                </div>
                <div class="form-section-title"><span>02</span><div><strong>Kebutuhan material / BoM</strong><small>Isi qty setiap bahan untuk jumlah hasil acuan di atas. Satuan mengikuti katalog material.</small></div></div>
                @if($materials->isEmpty())
                    <div class="catalog-note"><p>Belum ada material. Buat material pertama untuk menyusun BoM produk ini. Isian produk tetap tersimpan.</p><button type="button" class="button button-secondary" wire:click="openMaterial">Buat material</button></div>
                @endif
                <div class="catalog-bom-list">
                    @foreach($bom as $index => $row)
                        @php($selectedMaterial = $materials->firstWhere('id', (int) ($row['material_id'] ?? 0)))
                        <div class="catalog-bom-row" wire:key="bom-{{ $editingId ?? 'new' }}-{{ $index }}">
                            <label class="field"><span class="field-label">Material {{ $index + 1 }} *</span><select wire:model.live="bom.{{ $index }}.material_id" required><option value="">Pilih material</option>@foreach($materials as $material)<option value="{{ $material->id }}">{{ $material->name }} ({{ $material->uom }})</option>@endforeach</select></label>
                            <label class="field"><span class="field-label">Qty kebutuhan *</span><input type="number" wire:model="bom.{{ $index }}.quantity" min="0.001" max="999999999" step="0.001" required placeholder="Contoh: 25" /></label>
                            <div class="field"><span class="field-label">UoM</span><span class="catalog-uom">{{ $selectedMaterial?->uom ?? 'Pilih material' }}</span></div>
                            <button type="button" class="button button-ghost" wire:click="removeMaterialRow({{ $index }})" @disabled(count($bom) <= 1) aria-label="Hapus material baris {{ $index + 1 }}">Hapus</button>
                        </div>
                    @endforeach
                </div>
                <div class="catalog-bom-actions"><button type="button" class="button button-secondary" wire:click="addMaterialRow" @disabled(count($bom) >= 100)>+ Tambah material</button><button type="button" class="button button-ghost" wire:click="openMaterial">Buat material baru</button></div>
                <div class="form-footer"><button type="button" class="button button-secondary" wire:click="closeModal">Batal</button><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="saveProduct" @disabled($materials->isEmpty())><span wire:loading.remove wire:target="saveProduct">{{ $editingId ? 'Simpan perubahan produk' : 'Simpan produk & BoM' }}</span><span wire:loading wire:target="saveProduct">Menyimpan...</span></button></div>
            </form>
