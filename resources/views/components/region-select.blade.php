@props(['provinceId' => null, 'cityId' => null, 'required' => true])
@php $picker = \App\Support\Regions::picker(); @endphp

{{-- Dropdown Provinsi -> Kota/Kabupaten. Data dikirim sekali sebagai JSON (tanpa request tambahan).
     Mengirim event "city-changed" (detail = id kota) agar induknya bisa menghitung ongkir. --}}
<div x-data="regionSelect(@js($picker), @js($provinceId), @js($cityId))" x-init="notify()" class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-2" for="province_id">Provinsi {{ $required ? '*' : '' }}</label>
        <select id="province_id" name="province_id" x-model="provinceId" @change="cityId = ''; notify()" {{ $required ? 'required' : '' }}
                class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
            <option value="">Pilih provinsi</option>
            <template x-for="p in provinces" :key="p.id">
                <option :value="p.id" x-text="p.name" :selected="String(p.id) === String(provinceId)"></option>
            </template>
        </select>
        @error('province_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium mb-2" for="city_id">Kota / Kabupaten {{ $required ? '*' : '' }}</label>
        <select id="city_id" name="city_id" x-model="cityId" @change="notify()" :disabled="!provinceId" {{ $required ? 'required' : '' }}
                class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500 disabled:bg-stone-50">
            <option value="">Pilih kota / kabupaten</option>
            <template x-for="c in cityList" :key="c.id">
                <option :value="c.id" x-text="c.name" :selected="String(c.id) === String(cityId)"></option>
            </template>
        </select>
        @error('city_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
</div>

@once
<script>
    window.regionSelect = function (data, provinceId, cityId) {
        return {
            provinces: data.provinces,
            cities: data.cities,
            provinceId: provinceId || '',
            cityId: cityId || '',
            get cityList() { return this.cities[this.provinceId] || []; },
            notify() { this.$dispatch('city-changed', this.cityId || null); },
        };
    };
</script>
@endonce
