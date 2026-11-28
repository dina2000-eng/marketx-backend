@extends('admin.layout')

@section('title', 'لوحة التحكم')
@section('page-title', 'لوحة التحكم')
@section('page-description', 'نظرة عامة على أداء منصة Vendo وإدارة عملياتها')

@section('content')
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:15px;margin-bottom:24px;">

    @php
        $statCards = [
            ['label' => 'المنتجات المعلّقة', 'value' => $stats['pending_products'], 'icon' => '⏳', 'color' => '#fff7e6'],
            ['label' => 'إجمالي المنتجات', 'value' => $stats['total_products'], 'icon' => '📦', 'color' => '#eef2ff'],
            ['label' => 'المتاجر', 'value' => $stats['total_stores'], 'icon' => '🏪', 'color' => '#e8f7ef'],
            ['label' => 'طلبات التجار المعلّقة', 'value' => $stats['pending_vendor_requests'], 'icon' => '👤', 'color' => '#f5edff'],
            ['label' => 'إجمالي الطلبات', 'value' => $stats['total_orders'], 'icon' => '🛒', 'color' => '#e8f5ff'],
            ['label' => 'التصنيفات', 'value' => $stats['total_categories'], 'icon' => '🏷️', 'color' => '#fff0f0'],
        ];
    @endphp

    @foreach ($statCards as $card)
        <div class="panel" style="padding:19px;">
            <div style="width:42px;height:42px;border-radius:11px;background:{{ $card['color'] }};display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:14px;">
                {{ $card['icon'] }}
            </div>
            <div style="color:#718096;font-size:13px;margin-bottom:8px;">
                {{ $card['label'] }}
            </div>
            <strong style="font-size:27px;color:#202938;">
                {{ $card['value'] }}
            </strong>
        </div>
    @endforeach
</div>

@if (session('success'))
    <div style="padding:14px;border-radius:9px;background:#e8f7ef;color:#146c43;margin-bottom:18px;">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div style="padding:14px;border-radius:9px;background:#fff0f0;color:#a52828;margin-bottom:18px;">
        {{ $errors->first() }}
    </div>
@endif

<div class="panel" style="margin-bottom:22px;">
    <div class="panel-heading">
        <div>
            <h2 style="margin:0;font-size:18px;">طلبات الانضمام كتاجر</h2>
            <p style="margin:7px 0 0;color:#718096;font-size:13px;">
                مراجعة طلبات التسجيل الجديدة للتجار
            </p>
        </div>
        <span class="status">{{ $vendorRequests->count() }} طلب</span>
    <a href="{{ route('admin.vendor-requests') }}"
   style="display:inline-block;margin-top:10px;color:var(--primary);font-size:13px;text-decoration:none;font-weight:600;">
    عرض جميع طلبات البائعين ←
</a>
    </div>

    @forelse ($vendorRequests as $vendorRequest)
        <article style="padding:17px 0;border-bottom:1px solid #edf0f5;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <strong style="font-size:16px;">{{ $vendorRequest->business_name }}</strong>
                    <div style="color:#94a3b8;font-size:12px;margin-top:5px;">
                        رقم الطلب #{{ $vendorRequest->id }}
                    </div>
                </div>

                <span class="status pending">بانتظار المراجعة</span>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:14px;color:#64748b;font-size:13px;">
                <div>
                    <span style="color:#94a3b8;">اسم المستخدم</span>
                    <div style="margin-top:4px;color:#334155;">
                        {{ $vendorRequest->user?->name ?? 'غير متوفر' }}
                    </div>
                </div>
                <div>
                    <span style="color:#94a3b8;">البريد الإلكتروني</span>
                    <div style="margin-top:4px;color:#334155;">
                        {{ $vendorRequest->user?->email ?? 'غير متوفر' }}
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;margin-top:15px;">
                <form method="POST"
                      action="{{ route('admin.vendor-requests.approve', $vendorRequest) }}"
                      onsubmit="return confirm('هل أنت متأكد من الموافقة على طلب هذا التاجر؟')">
                    @csrf
                    <button class="btn btn-success" type="submit">
                        الموافقة على طلب التاجر
                    </button>
                </form>

                <form method="POST"
                      action="{{ route('admin.vendor-requests.reject', $vendorRequest) }}"
                      style="display:flex;gap:8px;flex:1;min-width:240px;"
                      onsubmit="return confirm('هل أنت متأكد من رفض طلب هذا التاجر؟')">
                    @csrf
                    <input type="text"
                           name="rejection_reason"
                           placeholder="سبب الرفض"
                           maxlength="1000"
                           required
                           style="flex:1;min-width:120px;">
                    <button class="btn btn-danger" type="submit">
                        رفض الطلب
                    </button>
                </form>
            </div>
        </article>
    @empty
        <div style="padding:25px;text-align:center;color:#94a3b8;">
            لا توجد طلبات تجار معلّقة حالياً.
        </div>
    @endforelse
</div>

<div class="panel">
    <div class="panel-heading">
        <div>
            <h2 style="margin:0;font-size:18px;">المنتجات بانتظار الموافقة</h2>
            <p style="margin:7px 0 0;color:#718096;font-size:13px;">
                مراجعة المنتجات المقدمة من المتاجر قبل نشرها
            </p>
        </div>
        <span class="status pending">{{ $products->total() }} منتج</span>
    </div>

    @forelse ($products as $product)
        <article style="padding:18px 0;border-bottom:1px solid #edf0f5;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <strong style="font-size:16px;">{{ $product->name }}</strong>
                    <div style="color:#94a3b8;font-size:12px;margin-top:5px;">
                        رقم المنتج #{{ $product->id }}
                    </div>
                </div>
                <span class="status pending">بانتظار الموافقة</span>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:15px;">
                <div>
                    <div style="color:#94a3b8;font-size:12px;">المتجر</div>
                    <strong style="font-size:13px;">{{ $product->store?->name ?? 'غير محدد' }}</strong>
                </div>
                <div>
                    <div style="color:#94a3b8;font-size:12px;">التصنيف</div>
                    <strong style="font-size:13px;">{{ $product->category?->name ?? 'بدون تصنيف' }}</strong>
                </div>
                <div>
                    <div style="color:#94a3b8;font-size:12px;">السعر</div>
                    <strong style="font-size:14px;color:var(--primary);">
                        {{ $product->base_price }} {{ $product->currency }}
                    </strong>
                </div>
            </div>

            @if ($product->description)
                <p style="color:#64748b;font-size:13px;line-height:1.8;margin:13px 0 0;">
                    {{ $product->description }}
                </p>
            @endif

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
                <form method="POST" action="{{ route('admin.products.approve', $product) }}">
                    @csrf
                    <button class="btn btn-success" type="submit">الموافقة على المنتج</button>
                </form>

                <form method="POST"
                      action="{{ route('admin.products.reject', $product) }}"
                      style="display:flex;gap:8px;flex-wrap:wrap;flex:1;min-width:240px;"
                      onsubmit="return confirm('هل أنت متأكد من رفض هذا المنتج؟')">
                    @csrf
                    <input
                        type="text"
                        name="rejection_reason"
                        placeholder="سبب الرفض (مطلوب)"
                        maxlength="1000"
                        required
                        style="flex:1;min-width:180px;"
                    >
                    <button class="btn btn-danger" type="submit">رفض المنتج</button>
                </form>
            </div>
        </article>
    @empty
        <div style="padding:25px;text-align:center;color:#94a3b8;">
            لا توجد منتجات بانتظار المراجعة حالياً.
        </div>
    @endforelse

    @if ($products->hasPages())
        <div style="display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin-top:24px;padding-top:18px;border-top:1px solid #edf0f5;">
            @if ($products->onFirstPage())
                <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">السابق</span>
            @else
                <a href="{{ $products->previousPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">السابق</a>
            @endif

            @for ($page = 1; $page <= $products->lastPage(); $page++)
                <a href="{{ $products->url($page) }}"
                   style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid {{ $products->currentPage() === $page ? 'var(--primary)' : '#e5e7eb' }};border-radius:7px;background:{{ $products->currentPage() === $page ? 'var(--primary)' : '#fff' }};color:{{ $products->currentPage() === $page ? '#fff' : '#64748b' }};font-size:12px;text-decoration:none;">
                    {{ $page }}
                </a>
            @endfor

            @if ($products->hasMorePages())
                <a href="{{ $products->nextPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">التالي</a>
            @else
                <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">التالي</span>
            @endif
        </div>

        <div style="text-align:center;color:#94a3b8;font-size:12px;margin-top:10px;">
            عرض {{ $products->firstItem() }}–{{ $products->lastItem() }} من {{ $products->total() }} منتج
        </div>
    @endif
</div>
@endsection
