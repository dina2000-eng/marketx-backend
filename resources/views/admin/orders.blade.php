
cat > resources/views/admin/orders.blade.php <<'EOF'
@extends('admin.layout')

@section('title', 'إدارة الطلبات')
@section('page-title', 'إدارة الطلبات')
@section('page-description', 'متابعة طلبات العملاء وحالات الدفع والتنفيذ')

@section('content')
<div class="panel">
    <div class="panel-heading">
        <div>
            <h2 style="margin:0;font-size:18px;">جميع الطلبات</h2>
            <p style="margin:7px 0 0;color:#718096;font-size:13px;">
                تفاصيل الطلبات وإدارة مراحل تنفيذها
            </p>
        </div>
        <span class="status">{{ $orders->total() }} طلب</span>
    </div>

    @if ($orders->isNotEmpty())
        <div style="display:grid;gap:18px;">
            @foreach ($orders as $order)
                @php
                    $payment = $order->payments->sortByDesc('id')->first();

                    $statusLabels = [
                        'pending' => 'قيد الانتظار',
                        'confirmed' => 'تم التأكيد',
                        'processing' => 'قيد التجهيز',
                        'shipped' => 'تم الشحن',
                        'delivered' => 'تم التسليم',
                        'cancelled' => 'ملغي',
                    ];

                    $paymentStatus = $payment?->status ?? $order->payment_status;
                    $paymentLabels = [
                        'pending' => 'قيد الانتظار',
                        'paid' => 'مدفوع',
                        'unpaid' => 'غير مدفوع',
                        'failed' => 'فشل الدفع',
                        'refunded' => 'مسترد',
                    ];
                @endphp

                <article style="border:1px solid #edf0f5;border-radius:12px;padding:18px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                        <div>
                            <strong style="font-size:17px;">الطلب #{{ $order->id }}</strong>
                            <div style="color:#94a3b8;font-size:12px;margin-top:5px;">
                                {{ $order->created_at?->format('Y-m-d H:i') }}
                            </div>
                        </div>

                        <span class="status {{ $order->status }}">
                            {{ $statusLabels[$order->status] ?? $order->status }}
                        </span>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;background:#f8fafc;border-radius:9px;padding:14px;">
                        <div>
                            <div style="color:#94a3b8;font-size:12px;">العميل</div>
                            <strong>{{ $order->user?->name ?? 'غير متوفر' }}</strong>
                        </div>

                        <div>
                            <div style="color:#94a3b8;font-size:12px;">المستأجر</div>
                            <strong>{{ $order->tenant?->name ?? $order->tenant_id }}</strong>
                        </div>

                        <div>
                            <div style="color:#94a3b8;font-size:12px;">إجمالي الطلب</div>
                            <strong>{{ $order->total_amount }} {{ $order->currency }}</strong>
                        </div>

                        <div>
                            <div style="color:#94a3b8;font-size:12px;">طريقة الدفع</div>
                            <strong>{{ $order->payment_method ?: 'غير محددة' }}</strong>
                        </div>

                        <div>
                            <div style="color:#94a3b8;font-size:12px;">حالة الدفع</div>
                            <span class="status {{ $paymentStatus }}">
                                {{ $paymentLabels[$paymentStatus] ?? ($paymentStatus ?: 'غير محددة') }}
                            </span>
                        </div>
                    </div>

                    <div style="margin-top:18px;">
                        <h4 style="margin:0 0 12px;font-size:14px;">المنتجات والمتاجر</h4>

                        <div style="display:grid;gap:9px;">
                            @forelse ($order->items as $item)
                                <div style="padding:12px;border:1px solid #edf0f5;border-radius:8px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                    <div>
                                        <strong>{{ $item->product_name }}</strong>
                                        <div style="color:#718096;font-size:12px;margin-top:4px;">
                                            {{ $item->variant_name ?? 'بدون خيار' }}
                                        </div>
                                    </div>
                                    <div style="color:#64748b;font-size:13px;">
                                        الكمية: {{ $item->quantity }}
                                        <span style="margin:0 8px;">|</span>
                                        المتجر: {{ $item->store?->name ?? 'غير متوفر' }}
                                    </div>
                                </div>
                            @empty
                                <div style="color:#94a3b8;font-size:13px;">لا توجد عناصر لهذا الطلب.</div>
                            @endforelse
                        </div>
                    </div>

                    @if (!in_array($order->status, ['cancelled', 'delivered'], true))
                        <form style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px;padding-top:15px;border-top:1px solid #edf0f5;"
                              method="POST"
                              action="{{ route('admin.orders.status', $order) }}"
                              onsubmit="return confirm('هل تريد تحديث حالة الطلب؟')">
                            @csrf

                            <label for="status-{{ $order->id }}" style="font-size:13px;color:#64748b;">
                                تحديث حالة الطلب:
                            </label>

                            <select id="status-{{ $order->id }}" name="status" required>
                                @foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'] as $status)
                                    <option value="{{ $status }}"
                                        @selected($order->status === $status)>
                                        {{ $statusLabels[$status] }}
                                    </option>
                                @endforeach
                            </select>

                            <button class="btn btn-primary" type="submit">حفظ التحديث</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @else
        <div style="padding:35px;text-align:center;color:#718096;">
            لا توجد طلبات حالياً.
        </div>
    @endif


@if ($orders->hasPages())
    <div style="display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin-top:24px;padding-top:18px;border-top:1px solid #edf0f5;">

        @if ($orders->onFirstPage())
            <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">السابق</span>
        @else
            <a href="{{ $orders->previousPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">السابق</a>
        @endif

        @for ($page = 1; $page <= $orders->lastPage(); $page++)
            <a href="{{ $orders->url($page) }}"
               style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid {{ $orders->currentPage() === $page ? 'var(--primary)' : '#e5e7eb' }};border-radius:7px;background:{{ $orders->currentPage() === $page ? 'var(--primary)' : '#fff' }};color:{{ $orders->currentPage() === $page ? '#fff' : '#64748b' }};font-size:12px;text-decoration:none;">
                {{ $page }}
            </a>
        @endfor

        @if ($orders->hasMorePages())
            <a href="{{ $orders->nextPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">التالي</a>
        @else
            <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">التالي</span>
        @endif
    </div>

    <div style="text-align:center;color:#94a3b8;font-size:12px;margin-top:10px;">
        عرض {{ $orders->firstItem() }}–{{ $orders->lastItem() }} من {{ $orders->total() }} طلب
    </div>
@endif

</div>
@endsection
