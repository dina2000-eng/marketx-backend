
cat > resources/views/admin/payouts.blade.php <<'EOF'
@extends('admin.layout')

@section('title', 'إدارة طلبات السحب')
@section('page-title', 'إدارة طلبات السحب')
@section('page-description', 'مراجعة طلبات سحب أرصدة المتاجر وإدارة عمليات الدفع')

@section('content')
@php
    $statusLabels = [
        'pending' => 'قيد الانتظار',
        'processing' => 'قيد المعالجة',
        'paid' => 'تم الدفع',
        'failed' => 'فشل الدفع',
        'cancelled' => 'ملغي',
    ];
@endphp

<div class="panel">
    <div class="panel-heading">
        <div>
            <h2 style="margin:0;font-size:18px;">طلبات السحب</h2>
            <p style="margin:7px 0 0;color:#718096;font-size:13px;">
                تفاصيل طلبات السحب وحالة كل عملية
            </p>
        </div>
        <span class="status">{{ $payouts->total() }} طلب</span>
    </div>

    @if ($payouts->isNotEmpty())
        <div style="display:grid;gap:18px;">
            @foreach ($payouts as $payout)
                <article style="border:1px solid #edf0f5;border-radius:12px;padding:19px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:17px;">
                        <div>
                            <strong style="font-size:17px;">طلب سحب #{{ $payout->id }}</strong>
                            <div style="color:#94a3b8;font-size:12px;margin-top:5px;">
                                {{ $payout->requested_at?->format('Y-m-d H:i') ?? '—' }}
                            </div>
                        </div>

                        <span class="status {{ $payout->status }}">
                            {{ $statusLabels[$payout->status] ?? $payout->status }}
                        </span>
                    </div>

                    <div style="background:#f8fafc;border-radius:10px;padding:17px;margin-bottom:16px;">
                        <div style="color:#94a3b8;font-size:12px;margin-bottom:6px;">المبلغ المطلوب</div>
                        <div style="font-size:25px;font-weight:700;color:var(--primary);">
                            {{ $payout->amount }}
                            <span style="font-size:14px;">{{ $payout->currency }}</span>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;">
                        <div>
                            <div style="color:#94a3b8;font-size:12px;">المتجر</div>
                            <strong>{{ $payout->store?->name ?? 'غير متوفر' }}</strong>
                        </div>

                        <div>
                            <div style="color:#94a3b8;font-size:12px;">مالك المتجر</div>
                            <strong>{{ $payout->store?->owner?->name ?? 'غير متوفر' }}</strong>
                        </div>

                        <div>
                            <div style="color:#94a3b8;font-size:12px;">المستأجر</div>
                            <strong>{{ $payout->store?->tenant?->name ?? $payout->store?->tenant_id ?? '—' }}</strong>
                        </div>

                        <div>
                            <div style="color:#94a3b8;font-size:12px;">طريقة السحب</div>
                            <strong>{{ $payout->method }}</strong>
                        </div>

                        <div>
                            <div style="color:#94a3b8;font-size:12px;">الرقم المرجعي</div>
                            <strong>{{ $payout->reference ?? '—' }}</strong>
                        </div>
                    </div>

                    @if ($payout->destination_details)
                        <div style="margin-top:18px;padding:15px;background:#f8fafc;border:1px solid #edf0f5;border-radius:9px;">
                            <strong style="display:block;font-size:14px;margin-bottom:10px;">
                                بيانات وجهة السحب
                            </strong>

                            <pre style="margin:0;white-space:pre-wrap;overflow-wrap:anywhere;font-family:inherit;font-size:13px;line-height:1.9;color:#64748b;">{{ json_encode($payout->destination_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif

                    @if (in_array($payout->status, ['pending', 'processing']))
                        <form method="POST"
                              action="{{ route('admin.payouts.status', $payout) }}"
                              style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px;padding-top:16px;border-top:1px solid #edf0f5;">
                            @csrf

                            <select name="status" required>
                                @if ($payout->status === 'pending')
                                    <option value="processing">قيد المعالجة</option>
                                @endif
                                <option value="paid">تم الدفع</option>
                                <option value="failed">فشل الدفع</option>
                                @if ($payout->status === 'pending')
                                    <option value="cancelled">إلغاء</option>
                                @endif
                            </select>

                            <input type="text"
                                   name="reference"
                                   maxlength="191"
                                   placeholder="رقم مرجع الدفع (اختياري)"
                                   style="flex:1;min-width:200px;">

                            <button class="btn btn-primary"
                                    type="submit"
                                    onclick="return confirm('هل أنت متأكد من تحديث حالة طلب السحب؟')">
                                حفظ التحديث
                            </button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @else
        <div style="padding:35px;text-align:center;color:#718096;">
            لا توجد طلبات سحب حالياً.
        </div>
    @endif

    @if ($payouts->hasPages())
        <div style="display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin-top:24px;padding-top:18px;border-top:1px solid #edf0f5;">
            @if ($payouts->onFirstPage())
                <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">السابق</span>
            @else
                <a href="{{ $payouts->previousPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">السابق</a>
            @endif

            @for ($page = 1; $page <= $payouts->lastPage(); $page++)
                <a href="{{ $payouts->url($page) }}"
                   style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid {{ $payouts->currentPage() === $page ? 'var(--primary)' : '#e5e7eb' }};border-radius:7px;background:{{ $payouts->currentPage() === $page ? 'var(--primary)' : '#fff' }};color:{{ $payouts->currentPage() === $page ? '#fff' : '#64748b' }};font-size:12px;text-decoration:none;">
                    {{ $page }}
                </a>
            @endfor

            @if ($payouts->hasMorePages())
                <a href="{{ $payouts->nextPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">التالي</a>
            @else
                <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">التالي</span>
            @endif
        </div>

        <div style="text-align:center;color:#94a3b8;font-size:12px;margin-top:10px;">
            عرض {{ $payouts->firstItem() }}–{{ $payouts->lastItem() }} من {{ $payouts->total() }} طلب
        </div>
    @endif
</div>
@endsection
