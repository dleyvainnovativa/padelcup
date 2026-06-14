{{-- reusable flash message --}}
@if (session('status'))
<div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-3"
    style="font-size:13px;border-radius:var(--radius);background:var(--success-soft);color:var(--success-text);border:1px solid transparent;">
    <i class="fa-solid fa-circle-check"></i> {{ session('status') }}
</div>
@endif