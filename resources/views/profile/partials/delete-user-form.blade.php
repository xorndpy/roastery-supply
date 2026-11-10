<section class="space-y-6">
    <header>
        <h2 class="text-base font-semibold text-stone-900">
            {{ 'Hapus akun' }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ 'Akun yang dihapus tidak bisa dikembalikan. Pastikan data yang perlu disimpan sudah Anda cadangkan terlebih dahulu.' }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ 'Hapus akun' }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900">
                {{ 'Yakin ingin menghapus akun Anda?' }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ 'Masukkan kata sandi Anda untuk mengonfirmasi penghapusan akun secara permanen.' }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ 'Kata sandi' }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ 'Kata sandi' }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ 'Batal' }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ 'Hapus akun' }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
