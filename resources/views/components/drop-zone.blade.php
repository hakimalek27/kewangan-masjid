@props([
    'name' => 'dokumen[]',
    'id' => 'dokumen',
    'label' => 'Dokumen Sokongan (boleh pilih beberapa fail)',
])

{{-- Fasa 9 UX — zon seret & lepas resit (Alpine). Fail digabung ke input[type=file] sedia ada. --}}
<div class="mb-3" x-data="{
        seret: false,
        fail: [],
        letak(e) {
            this.seret = false;
            const dt = new DataTransfer();
            [...this.$refs.input.files, ...e.dataTransfer.files].forEach((f) => dt.items.add(f));
            this.$refs.input.files = dt.files;
            this.kemaskini();
        },
        buang(i) {
            const dt = new DataTransfer();
            [...this.$refs.input.files].forEach((f, j) => { if (j !== i) dt.items.add(f); });
            this.$refs.input.files = dt.files;
            this.kemaskini();
        },
        kemaskini() { this.fail = [...this.$refs.input.files].map((f) => f.name + ' (' + Math.round(f.size / 1024) + ' KB)'); }
     }">
    <label class="form-label" for="{{ $id }}">{{ __($label) }}</label>
    <div class="drop-zone text-center p-3" role="button"
         :class="{ 'drop-aktif': seret }"
         @dragover.prevent="seret = true"
         @dragleave.prevent="seret = false"
         @drop.prevent="letak($event)"
         @click="$refs.input.click()">
        <i class="bi bi-cloud-arrow-up fs-3 d-block"></i>
        <span class="small">{{ __('Seret & lepas resit/dokumen di sini — atau klik untuk pilih fail') }}</span>
    </div>
    <input type="file" name="{{ $name }}" id="{{ $id }}" class="d-none" multiple x-ref="input" @change="kemaskini()">
    <ul class="list-unstyled small mt-2 mb-0">
        <template x-for="(f, i) in fail" :key="i">
            <li class="text-muted">
                <i class="bi bi-paperclip me-1"></i><span x-text="f"></span>
                <a href="#" class="text-danger ms-2" @click.prevent="buang(i)" title="{{ __('Buang fail') }}"><i class="bi bi-x-circle"></i></a>
            </li>
        </template>
    </ul>
</div>
