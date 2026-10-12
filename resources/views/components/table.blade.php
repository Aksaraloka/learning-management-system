{{--
    Tabel responsif. Isi <thead>/<tbody> lewat slot; beri `data-label` pada setiap <td>
    agar di HP baris berubah jadi kartu berlabel.
--}}
<div {{ $attributes->class('overflow-hidden sm:rounded-card sm:border sm:border-border sm:bg-surface') }}>
    <table class="table-responsive">{{ $slot }}</table>
</div>
