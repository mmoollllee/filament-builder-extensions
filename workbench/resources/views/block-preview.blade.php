{{-- Minimal block preview used by the workbench nested-builder demo: it shows the block's
     title, which the editable title in the header sends at once for a block with a preview. --}}
<div class="fbt-block-preview">
    {{ $title ?? $body ?? 'Preview' }}
</div>
