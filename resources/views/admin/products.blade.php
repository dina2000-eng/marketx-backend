@extends('admin.layout')

@section('title', 'Vendo | إدارة المنتجات')
@section('page-title', 'إدارة المنتجات')
@section('page-description', 'مراجعة المنتجات ومتابعة حالتها في المنصة')

@section('content')
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>جميع المنتجات</h2>
            <p>عرض ومراجعة المنتجات المسجلة في المنصة</p>
        </div>
        <span class="status active">{{ $products->total() }} منتج</span>
    </div>

    <form method="GET" action="{{ route('admin.products') }}"
          style="display:flex;gap:10px;flex-wrap:wrap;margin:20px 0">
        <input type="text" name="search" value="{{ $search }}"
               placeholder="ابحث باسم المنتج أو رقمه..."
               style="flex:1;min-width:200px">

        <select name="status" style="min-width:170px">
            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>كل الحالات</option>
            <option value="pending_review" {{ $status === 'pending_review' ? 'selected' : '' }}>قيد المراجعة</option>
            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>مقبول</option>
            <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>مرفوض</option>
        </select>

        <button class="btn btn-success" type="submit">بحث وتصفية</button>
        <a href="{{ route('admin.products') }}"
           class="btn btn-warning" style="text-decoration:none">إعادة ضبط</a>
    </form>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;min-width:1000px;text-align:right">
            <thead>
                <tr style="background:#f7f8fc;color:#858ba0;font-size:13px">
                    <th style="padding:15px">المنتج</th>
                    <th style="padding:15px">المتجر</th>
                    <th style="padding:15px">التصنيف</th>
                    <th style="padding:15px">السعر</th>
                    <th style="padding:15px">الحالة</th>
                    <th style="padding:15px">تاريخ الإضافة</th>
                    <th style="padding:15px">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr style="border-bottom:1px solid #eceef4">
                        <td style="padding:15px">
                            <div style="display:flex;align-items:center;gap:10px">
                                @if ($product->images->isNotEmpty())
                                    <img src="{{ asset('storage/'.$product->images->first()->image_url) }}"
                                         alt="{{ $product->name }}"
                                         style="width:52px;height:52px;object-fit:cover;border-radius:9px">
                                @else
                                    <div style="width:52px;height:52px;background:#f1f3f7;border-radius:9px;display:flex;align-items:center;justify-content:center;color:#9ca3af">
                                        —
                                    </div>
                                @endif

                                <div>
                                    <strong>{{ $product->name }}</strong>
                                    <div style="color:#858ba0;font-size:12px;margin-top:5px">
                                        #{{ $product->id }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td style="padding:15px">
                            {{ $product->store?->name ?? 'غير متوفر' }}
                        </td>

                        <td style="padding:15px">
                            {{ $product->category?->name ?? '—' }}
                        </td>

                        <td style="padding:15px">
                            {{ number_format((float) $product->base_price, 2) }}
                            {{ $product->currency }}
                        </td>

                        <td style="padding:15px">
                            <span class="status {{ $product->status === 'approved' ? 'active' : ($product->status === 'pending_review' ? 'pending' : 'rejected') }}">
                                @switch($product->status)
                                    @case('pending_review') قيد المراجعة @break
                                    @case('approved') مقبول @break
                                    @case('rejected') مرفوض @break
                                    @default {{ $product->status }}
                                @endswitch
                            </span>
                        </td>

                        <td style="padding:15px;color:#777e91;font-size:13px">
                            {{ $product->created_at?->format('Y-m-d') ?? '—' }}
                        </td>

                        <td style="padding:15px">
                            @if ($product->status === 'pending_review')
                                <div style="display:flex;flex-wrap:wrap;gap:7px;align-items:center">
                                    <form method="POST"
                                          action="{{ route('admin.products.approve', $product) }}">
                                        @csrf
                                        <button class="btn btn-success" type="submit">موافقة</button>
                                    </form>

                                    <form method="POST"
                                          action="{{ route('admin.products.reject', $product) }}"
                                          style="display:flex;flex-wrap:wrap;gap:6px">
                                        @csrf
                                        <input type="text" name="rejection_reason"
                                               placeholder="سبب الرفض" maxlength="1000"
                                               required style="width:130px">
                                        <button class="btn btn-danger" type="submit">رفض</button>
                                    </form>
                                </div>
                            @else
                                <span style="color:#858ba0;font-size:12px">لا توجد إجراءات</span>
                            @endif
                        </td>
                    </tr>

                    @if ($product->rejection_reason)
                        <tr style="background:#fff9f9">
                            <td colspan="7" style="padding:10px 15px;color:#bd3e4c;font-size:13px">
                                سبب الرفض: {{ $product->rejection_reason }}
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" style="padding:25px">
                            <div class="empty">لا توجد منتجات مطابقة لبحثك.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        @if ($products->hasPages())
            <div style="display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin-top:24px;padding-top:18px;border-top:1px solid #edf0f5;">
                @if ($products->onFirstPage())
                    <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">السابق</span>
                @else
                    <a href="{{ $products->previousPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">السابق</a>
                @endif

                @for ($page = 1; $page <= $products->lastPage(); $page++)
                    <a href="{{ $products->url($page) }}" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid {{ $products->currentPage() === $page ? 'var(--primary)' : '#e5e7eb' }};border-radius:7px;background:{{ $products->currentPage() === $page ? 'var(--primary)' : '#fff' }};color:{{ $products->currentPage() === $page ? '#fff' : '#64748b' }};font-size:12px;text-decoration:none;">{{ $page }}</a>
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
</section>
@endsection
