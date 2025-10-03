@extends('layouts.shopify')

@section('content')
    <div class="Polaris-Layout">
        <div class="Polaris-Layout__Section">
            <div class="Polaris-Card">
                <div class="Polaris-Card__Section">
                    <h2 class="Polaris-Heading">ERP Integration Setup</h2>
                    <p>Enter your ERP details below to connect with Shopify.</p>

                    <form method="POST" action="{{ route('shopify.erp.save', $shop->id) }}">
                        @csrf
                        <div class="Polaris-FormLayout">

                            <div class="Polaris-FormLayout__Item">
                                <label class="Polaris-Label">ERP URL</label>
                                <input class="Polaris-TextField__Input" type="url" name="erp_url"
                                    value="{{ old('erp_url', $shop->erpIntegration->erp_url ?? '') }}"
                                    placeholder="https://erp.example.com" required>
                            </div>

                            <div class="Polaris-FormLayout__Item">
                                <label class="Polaris-Label">ERP API Token</label>
                                <input class="Polaris-TextField__Input" type="password" name="erp_secret"
                                    value="{{ old('erp_secret', $shop->erpIntegration->erp_secret ?? '') }}" required>
                            </div>

                            <div class="Polaris-FormLayout__Item">
                                <button type="submit" class="Polaris-Button Polaris-Button--primary">
                                    Save ERP Settings
                                </button>
                            </div>

                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection
