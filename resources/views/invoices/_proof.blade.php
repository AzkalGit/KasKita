@if ($inv->proof_path)
    @if ($inv->proofAvailable())
        <a href="{{ route('invoices.proof_download', [$organization, $inv->id]) }}" class="font-semibold text-brand hover:text-brand-dark">Unduh bukti</a>
    @else
        <span class="text-mute">Berkas bukti tidak tersedia</span>
    @endif
@else
    <span class="text-mute">Tanpa bukti</span>
@endif