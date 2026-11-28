@extends('admin.layout')

@section('title', 'Vendo | طلبات البائعين')
@section('page-title', 'طلبات البائعين')
@section('page-description', 'مراجعة طلبات الانضمام ومتابعة حالتها')

@section('content')
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>جميع طلبات البائعين</h2>
            <p>البحث ومراجعة طلبات الانضمام إلى المنصة</p>
        </div>
        <span class="status active">{{ $vendorRequests->total() }} طلب</span>
    </div>

    <form method="GET" action="{{ route('admin.vendor-requests') }}"
          style="display:flex;gap:10px;flex-wrap:wrap;margin:20px 0">
        <input type="text" name="search" value="{{ $search }}"
               placeholder="ابحث بالاسم أو البريد أو الهاتف..."
               style="flex:1;min-width:220px">

        <select name="status" style="min-width:160px">
            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>جميع الحالات</option>
            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>بانتظار المراجعة</option>
            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>مقبول</option>
            <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>مرفوض</option>
        </select>

        <button class="btn btn-primary" type="submit">بحث وتصفية</button>
        <a href="{{ route('admin.vendor-requests') }}" class="btn">إعادة ضبط</a>
    </form>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;min-width:1050px;text-align:right">
            <thead>
                <tr style="background:#f7f8fc;color:#858ba0;font-size:13px">
                    <th style="padding:15px">النشاط التجاري</th>
                    <th style="padding:15px">مقدم الطلب</th>
                    <th style="padding:15px">معلومات التواصل</th>
                    <th style="padding:15px">المستأجر</th>
                    <th style="padding:15px">تاريخ الطلب</th>
                    <th style="padding:15px">الحالة</th>
                    <th style="padding:15px">الإجراءات</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($vendorRequests as $vendorRequest)
                    <tr style="border-bottom:1px solid #eceef4">
                        <td style="padding:17px 15px">
                            <strong>{{ $vendorRequest->business_name }}</strong>
                            <div style="color:#858ba0;font-size:12px;margin-top:5px">
                                #{{ $vendorRequest->id }}
                            </div>
                        </td>

                        <td style="padding:17px 15px">
                            {{ $vendorRequest->user?->name ?? 'غير متوفر' }}
                        </td>

                        <td style="padding:17px 15px;font-size:13px;color:#777e91">
                            <div>{{ $vendorRequest->email ?? $vendorRequest->user?->email ?? '—' }}</div>
                            <div style="margin-top:5px">
                                {{ $vendorRequest->phone ?? $vendorRequest->user?->phone ?? '—' }}
                            </div>
                        </td>

                        <td style="padding:17px 15px">
                            {{ $vendorRequest->tenant?->name ?? '—' }}
                        </td>

                        <td style="padding:17px 15px;font-size:13px">
                            {{ $vendorRequest->created_at?->format('Y-m-d') ?? '—' }}
                        </td>

                        <td style="padding:17px 15px">
                            <span class="status {{ $vendorRequest->status }}">
                                @switch($vendorRequest->status)
                                    @case('pending') بانتظار المراجعة @break
                                    @case('approved') مقبول @break
                                    @case('rejected') مرفوض @break
                                    @default {{ $vendorRequest->status }}
                                @endswitch
                            </span>
                        </td>

                        <td style="padding:17px 15px">
                            @if ($vendorRequest->status === 'pending')
                                <div style="display:flex;flex-wrap:wrap;gap:7px;align-items:center">
                                    <form method="POST"
                                          action="{{ route('admin.vendor-requests.approve', $vendorRequest) }}"
                                          onsubmit="return confirm('هل تريد الموافقة على هذا الطلب؟')">
                                        @csrf
                                        <button class="btn btn-success" type="submit">موافقة</button>
                                    </form>

                                    <form method="POST"
                                          action="{{ route('admin.vendor-requests.reject', $vendorRequest) }}"
                                          style="display:flex;flex-wrap:wrap;gap:6px">
                                        @csrf
                                        <input type="text" name="rejection_reason"
                                               placeholder="سبب الرفض" maxlength="1000"
                                               required style="width:130px">
                                        <button class="btn btn-danger" type="submit"
                                                onclick="return confirm('هل تريد رفض هذا الطلب؟')">
                                            رفض
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span style="color:#858ba0;font-size:12px">تمت المراجعة</span>
                            @endif
                        </td>
                    </tr>

                    @if ($vendorRequest->rejection_reason || $vendorRequest->reviewed_at)
                        <tr style="background:#f9fafc">
                            <td colspan="7" style="padding:12px 15px;font-size:13px;color:#777e91">
                                @if ($vendorRequest->rejection_reason)
                                    <div style="color:#bd3e4c;margin-bottom:5px">
                                        سبب الرفض: {{ $vendorRequest->rejection_reason }}
                                    </div>
                                @endif

                                @if ($vendorRequest->reviewed_at)
                                    <div>
                                        تمت المراجعة بواسطة:
                                        {{ $vendorRequest->reviewer?->name ?? 'غير متوفر' }}
                                        — {{ $vendorRequest->reviewed_at->format('Y-m-d H:i') }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" style="padding:25px">
                            <div class="empty">لا توجد طلبات تطابق البحث الحالي.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($vendorRequests->hasPages())
        <div style="display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin-top:24px;padding-top:18px;border-top:1px solid #edf0f5">
            @if ($vendorRequests->onFirstPage())
                <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px">السابق</span>
            @else
                <a href="{{ $vendorRequests->previousPageUrl() }}"
                   style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none">السابق</a>
            @endif

            @for ($page = 1; $page <= $vendorRequests->lastPage(); $page++)
                <a href="{{ $vendorRequests->url($page) }}"
                   style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid {{ $vendorRequests->currentPage() === $page ? 'var(--primary)' : '#e5e7eb' }};border-radius:7px;background:{{ $vendorRequests->currentPage() === $page ? 'var(--primary)' : '#fff' }};color:{{ $vendorRequests->currentPage() === $page ? '#fff' : '#64748b' }};font-size:12px;text-decoration:none">
                    {{ $page }}
                </a>
            @endfor

            @if ($vendorRequests->hasMorePages())
                <a href="{{ $vendorRequests->nextPageUrl() }}"
                   style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none">التالي</a>
            @else
                <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px">التالي</span>
            @endif
        </div>

        <div style="text-align:center;color:#94a3b8;font-size:12px;margin-top:10px">
            عرض {{ $vendorRequests->firstItem() }}–{{ $vendorRequests->lastItem() }}
            من {{ $vendorRequests->total() }} طلب
        </div>
    @endif
</section>
@endsection
