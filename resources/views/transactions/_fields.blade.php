@php($t = $transaction ?? null)

<div>
    <x-input-label for="type" value="Jenis" />
    <x-select-input id="type" name="type" class="mt-1 block w-full">
        <option value="masuk" @selected(old('type', $t?->type) === 'masuk')>Pemasukan</option>
        <option value="keluar" @selected(old('type', $t?->type ?? 'keluar') === 'keluar')>Pengeluaran</option>
    </x-select-input>
    <x-input-error :messages="$errors->get('type')" class="mt-2" />
</div>

<div>
    <x-input-label for="category_id" value="Kategori" />
    <x-select-input id="category_id" name="category_id" class="mt-1 block w-full" required>
        <option value="">Pilih kategori</option>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" @selected((int) old('category_id', $t?->category_id) === $c->id)>{{ $c->name }}</option>
        @endforeach
    </x-select-input>
    <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
</div>

<div>
    <x-input-label for="amount" value="Jumlah (Rp, tanpa titik atau koma)" />
    <x-text-input id="amount" name="amount" type="number" min="1" step="1" class="mt-1 block w-full"
                  :value="old('amount', $t?->amount)" required />
    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
</div>

<div>
    <x-input-label for="tanggal" value="Tanggal" />
    <x-text-input id="tanggal" name="tanggal" type="date" class="mt-1 block w-full"
                  :value="old('tanggal', $t?->tanggal?->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
    <x-input-error :messages="$errors->get('tanggal')" class="mt-2" />
</div>

<div class="md:col-span-2">
    <x-input-label for="description" value="Keterangan" />
    <x-text-input id="description" name="description" maxlength="255" class="mt-1 block w-full"
                  :value="old('description', $t?->description)" required />
    <x-input-error :messages="$errors->get('description')" class="mt-2" />
</div>