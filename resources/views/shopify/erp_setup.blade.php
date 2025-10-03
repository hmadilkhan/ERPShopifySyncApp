@extends('layouts.shopify')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="bg-white shadow rounded-lg p-8">
            <h2 class="text-2xl font-semibold mb-6">ERP Integration Setup</h2>
            <p class="text-gray-600 mb-6">
                Enter your ERP details below to connect with Shopify.
            </p>

            <form action="{{ route('shopify.erp.save', $shop->id) }}" method="POST" class="space-y-6">
                @csrf

                {{-- ERP URL --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ERP URL</label>
                    <input type="url" name="erp_url" placeholder="https://erp.example.com"
                        value="{{ old('erp_url', $shop->erpIntegration->erp_url ?? '') }}"
                        class="block w-full border border-gray-300 rounded-md shadow-sm p-3 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('erp_url')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- ERP API Token --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ERP API Token</label>
                    <input type="text" name="erp_secret"
                        value="{{ old('erp_secret', $shop->erpIntegration->erp_secret ?? '') }}"
                        class="block w-full border border-gray-300 rounded-md shadow-sm p-3 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('erp_secret')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Save Button --}}
                <div class="flex justify-end">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-md font-medium">
                        Save ERP Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
