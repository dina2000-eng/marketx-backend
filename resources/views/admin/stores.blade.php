@extends('admin.layout')

@section('title', 'Vendo | إدارة المتاجر')
@section('page-title', 'إدارة المتاجر')
@section('page-description', 'متابعة المتاجر والمالكين وحالة كل متجر')

@section('content')
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>جميع المتاجر</h2>
            <p>إدارة ومراجعة المتاجر المسجلة في المنصة</p>
        </div>
        <span class="status active">{{ $stores->total() }} متجر</span>
    </div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;min-width:850px;text-align:right">
            <thead>
                <tr style="background:#f7f8fc;color:#858ba0;font-size:13px">
                    <th style="padding:15px">المتجر</th>
                    <th style="padding:15px">المالك</th>
                    <th style="padding:15px">معلومات التواصل</th>
                    <th style="padding:15px">المستأجر</th>
                    <th style="padding:15px">الحالة</th>
                    <th style="padding:15px">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stores as $store)
                    <tr style="border-bottom:1px solid #eceef4">
                        <td style="padding:17px 15px">
                            <strong>{{ $store->name }}</strong>
                            <div style="color:#858ba0;font-size:12px;margin-top:5px">
                                #{{ $store->id }}
                            </div>
                        </td>

                        <td style="padding:17px 15px">
                            {{ $store->owner?->name ?? 'غير متوفر' }}
                        </td>

                        <td style="padding:17px 15px;font-size:13px;color:#777e91">
                            <div>{{ $store->email ?? '—' }}</div>
                            <div style="margin-top:5px">{{ $store->phone ?? '—' }}</div>
                        </td>

                        <td style="padding:17px 15px">
                            {{ $store->tenant?->name ?? $store->tenant_id }}
                        </td>

                        <td style="padding:17px 15px">
                            <span class="status {{ $store->status }}">
                                @switch($store->status)
                                    @case('active') نشط @break
                                    @case('pending') قيد المراجعة @break
                                    @case('suspended') معلّق @break
                                    @case('rejected') مرفوض @break
                                    @default {{ $store->status }}
                                @endswitch
                            </span>
                        </td>

                        <td style="padding:17px 15px">
                            <div style="display:flex;flex-wrap:wrap;gap:7px;align-items:center">
                                @if ($store->status === 'pending')
                                    <form method="POST" action="{{ route('admin.stores.approve', $store) }}">
                                        @csrf
                                        <button class="btn btn-success" type="submit">موافقة</button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.stores.reject', $store) }}"
                                          style="display:flex;flex-wrap:wrap;gap:6px">
                                        @csrf
                                        <input type="text" name="rejection_reason"
                                               placeholder="سبب الرفض" maxlength="1000"
                                               required style="width:145px">
                                        <button class="btn btn-danger" type="submit">رفض</button>
                                    </form>
                                @elseif ($store->status === 'active')
                                    <form method="POST"
                                          action="{{ route('admin.stores.suspend', $store) }}"
                                          onsubmit="return confirm('هل تريد تعليق هذا المتجر؟')">
                                        @csrf
                                        <button class="btn btn-warning" type="submit">تعليق المتجر</button>
                                    </form>
                                @else
                                    <span style="color:#858ba0;font-size:12px">لا توجد إجراءات</span>
                                @endif
                            </div>
                        </td>
                    </tr>

                    @if ($store->rejection_reason)
                        <tr style="background:#fff9f9">
                            <td colspan="6" style="padding:10px 15px;color:#bd3e4c;font-size:13px">
                                سبب الرفض: {{ $store->rejection_reason }}
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" style="padding:25px">
                            <div class="empty">لا توجد متاجر حالياً.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        @if ($stores->hasPages())
    <div style="display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin-top:24px;padding-top:18px;border-top:1px solid #edf0f5;">
        @if ($stores->onFirstPage())
            <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">السابق</span>
        @else
            <a href="{{ $stores->previousPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">السابق</a>
        @endif

        @for ($page = 1; $page <= $stores->lastPage(); $page++)
            <a href="{{ $stores->url($page) }}" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid {{ $stores->currentPage() === $page ? 'var(--primary)' : '#e5e7eb' }};border-radius:7px;background:{{ $stores->currentPage() === $page ? 'var(--primary)' : '#fff' }};color:{{ $stores->currentPage() === $page ? '#fff' : '#64748b' }};font-size:12px;text-decoration:none;">{{ $page }}</a>
        @endfor

        @if ($stores->hasMorePages())
            <a href="{{ $stores->nextPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">التالي</a>
        @else
            <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">التالي</span>
        @endif
    </div>
    <div style="text-align:center;color:#94a3b8;font-size:12px;margin-top:10px;">
        عرض {{ $stores->firstItem() }}–{{ $stores->lastItem() }} من {{ $stores->total() }} متجر
    </div>
@endif
    </div>
</section>
@endsection
