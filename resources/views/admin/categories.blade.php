
cat > resources/views/admin/categories.blade.php <<'EOF'
@extends('admin.layout')

@section('title', 'إدارة التصنيفات')
@section('page-title', 'إدارة التصنيفات')
@section('page-description', 'عرض وإدارة تصنيفات المنتجات في المنصة')

@section('content')
<div class="panel">
    <div class="panel-heading">
        <div>
            <h2 style="margin:0;font-size:18px;">جميع التصنيفات</h2>
            <p style="margin:7px 0 0;color:#718096;font-size:13px;">
                إدارة التصنيفات الرئيسية والفرعية وحالاتها
            </p>
        </div>
        <span class="status">{{ $categories->total() }} تصنيف</span>
    </div>

    @if ($categories->isNotEmpty())
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:850px;">
                <thead>
                    <tr style="background:#f8fafc;color:#64748b;text-align:right;">
                        <th style="padding:14px;">التصنيف</th>
                        <th style="padding:14px;">المتجر / المستأجر</th>
                        <th style="padding:14px;">التصنيف الأب</th>
                        <th style="padding:14px;">المنتجات</th>
                        <th style="padding:14px;">الحالة</th>
                        <th style="padding:14px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr style="border-top:1px solid #edf0f5;">
                            <td style="padding:15px 14px;">
                                <strong>{{ $category->name }}</strong>
                                <div style="margin-top:5px;color:#94a3b8;font-size:12px;">
                                    #{{ $category->id }} · {{ $category->slug }}
                                </div>
                                @if ($category->children->isNotEmpty())
                                    <div style="margin-top:6px;color:#64748b;font-size:12px;">
                                        فرعي: {{ $category->children->pluck('name')->join('، ') }}
                                    </div>
                                @endif
                            </td>

                            <td style="padding:15px 14px;">
                                <div>{{ $category->store?->name ?? 'عام' }}</div>
                                <div style="margin-top:5px;color:#94a3b8;font-size:12px;">
                                    {{ $category->tenant?->name ?? $category->tenant_id }}
                                </div>
                            </td>

                            <td style="padding:15px 14px;color:#64748b;">
                                {{ $category->parent?->name ?? 'رئيسي' }}
                            </td>

                            <td style="padding:15px 14px;">
                                {{ $category->products->count() }}
                            </td>

                            <td style="padding:15px 14px;">
                                <span class="status {{ $category->is_active ? 'active' : 'inactive' }}">
                                    {{ $category->is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>

                            <td style="padding:15px 14px;">
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <form method="POST"
                                          action="{{ route('admin.categories.toggle-status', $category) }}">
                                        @csrf
                                        <button class="btn {{ $category->is_active ? 'btn-warning' : 'btn-success' }}"
                                                type="submit">
                                            {{ $category->is_active ? 'تعطيل' : 'تفعيل' }}
                                        </button>
                                    </form>

                                    <form method="POST"
                                          action="{{ route('admin.categories.delete', $category) }}"
                                          onsubmit="return confirm('هل أنت متأكد من حذف هذا التصنيف؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger" type="submit">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="padding:35px;text-align:center;color:#718096;">
            لا توجد تصنيفات حالياً.
        </div>
    @endif

    <div style="margin-top:20px;">
        @if ($categories->hasPages())
    <div style="display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin-top:24px;padding-top:18px;border-top:1px solid #edf0f5;">
        @if ($categories->onFirstPage())
            <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">السابق</span>
        @else
            <a href="{{ $categories->previousPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">السابق</a>
        @endif

        @for ($page = 1; $page <= $categories->lastPage(); $page++)
            <a href="{{ $categories->url($page) }}" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid {{ $categories->currentPage() === $page ? 'var(--primary)' : '#e5e7eb' }};border-radius:7px;background:{{ $categories->currentPage() === $page ? 'var(--primary)' : '#fff' }};color:{{ $categories->currentPage() === $page ? '#fff' : '#64748b' }};font-size:12px;text-decoration:none;">{{ $page }}</a>
        @endfor

        @if ($categories->hasMorePages())
            <a href="{{ $categories->nextPageUrl() }}" style="padding:7px 12px;border:1px solid #e5e7eb;border-radius:7px;color:#64748b;font-size:12px;text-decoration:none;">التالي</a>
        @else
            <span style="padding:7px 12px;border:1px solid #edf0f5;border-radius:7px;color:#cbd5e1;font-size:12px;">التالي</span>
        @endif
    </div>
    <div style="text-align:center;color:#94a3b8;font-size:12px;margin-top:10px;">
        عرض {{ $categories->firstItem() }}–{{ $categories->lastItem() }} من {{ $categories->total() }} تصنيف
    </div>
@endif
    </div>
</div>
@endsection
