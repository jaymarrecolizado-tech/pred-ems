{{-- Export button group for report pages. Expects $route (named route) and optional $params (query-string params). --}}
<div class="export-group" style="display:inline-flex; align-items:center; gap:6px">
    <span class="hint" style="font-size:12px; margin-right:2px">Export:</span>
    <a href="{{ route($route, array_merge($params ?? [], ['format' => 'csv'])) }}" class="btn btn-outline btn-sm">CSV</a>
    <a href="{{ route($route, array_merge($params ?? [], ['format' => 'xls'])) }}" class="btn btn-outline btn-sm" title="Excel workbook (.xls)">Excel</a>
    <a href="{{ route($route, array_merge($params ?? [], ['format' => 'pdf'])) }}" class="btn btn-outline btn-sm" title="PDF document (landscape)">PDF</a>
</div>
