@if ($inv->proof_path)
    @if ($inv->proofAvailable())
        <a href="{{ route('invoices.proof_download', [$organization, $inv->id]) }}" class="text-indigo-600 font-medium">Unduh bukti</a>
    @else
        <span class="text-gray-500">Berkas bukti tidak tersedia</span>
    @endif
@else
    <span class="text-gray-500">Tanpa bukti</span>
@endif