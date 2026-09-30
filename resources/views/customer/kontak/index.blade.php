@extends('layouts.app')

@section('title', 'Hubungi Kami')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <div class="max-w-3xl">
        <h1 class="text-4xl font-bold mb-2">Hubungi Kami</h1>
        <p class="text-stone-600 mb-8">Ada pertanyaan? Tim kami siap membantu.</p>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('contact.send') }}" class="space-y-6 bg-white p-8 rounded-lg border border-stone-200">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-2">Nama *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">No. Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Subjek *</label>
                    <input type="text" name="subject" value="{{ old('subject') }}" required
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('subject') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Pesan *</label>
                <textarea name="message" rows="6" required
                          class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">{{ old('message') }}</textarea>
                @error('message') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" 
                    class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition">
                Kirim Pesan
            </button>
        </form>
    </div>
</div>
@endsection