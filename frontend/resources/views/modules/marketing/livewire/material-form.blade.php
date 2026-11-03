<form class="entry-form catalog-modal-form" wire:submit="saveMaterial">
                <p class="required-fields-note">Material tersimpan pada master barang yang sama dengan IC. Pembuatan material tidak menambah stok.</p>
                <div class="form-grid two">
                    <label class="field"><span class="field-label">Nama material *</span><input type="text" wire:model="material_name" maxlength="255" required autofocus placeholder="Contoh: Copper scrap, arang, silica, Zn" /></label>
                    <label class="field"><span class="field-label">UoM / satuan *</span><input type="text" wire:model="material_uom" maxlength="30" required placeholder="Contoh: KG" /><small>Gunakan satuan yang sama saat mengisi qty pada BoM.</small></label>
                </div>
                <div class="form-footer"><button type="button" class="button button-secondary" wire:click="closeModal">{{ $modal === 'material-for-product' ? 'Kembali ke produk' : 'Batal' }}</button><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="saveMaterial"><span wire:loading.remove wire:target="saveMaterial">Simpan material</span><span wire:loading wire:target="saveMaterial">Menyimpan...</span></button></div>
            </form>
