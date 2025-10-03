@extends('layouts.app')

@section('content')
    <div class="max-w-lg mx-auto bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-bold mb-4">ERP Integration Setup</h2>
        <form action="{{ route('shopify.erp.save') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">ERP URL</label>
                <input type="text" name="erp_url" placeholder="https://erp.example.com"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">ERP API Token</label>
                <input type="text" name="erp_token"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                Save ERP Settings
            </button>
        </form>
    </div>
@endsection
