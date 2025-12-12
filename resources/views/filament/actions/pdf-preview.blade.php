<div style="width: 100%; height: 80vh;">
    @if($isImage ?? false)
        <img
            src="{{ $url }}"
            alt="Document preview"
            style="max-width: 100%; max-height: 100%; object-fit: contain; margin: 0 auto; display: block; border-radius: 0.5rem; border: 1px solid #e5e7eb;"
        />
    @else
        <iframe
            src="{{ $url }}"
            style="width: 100%; height: 100%; border-radius: 0.5rem; border: 1px solid #e5e7eb;"
        ></iframe>
    @endif
</div>
