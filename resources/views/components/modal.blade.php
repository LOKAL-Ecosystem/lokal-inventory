@props(['id', 'title', 'size' => 'max-w-lg'])

<dialog id="{{ $id }}" class="modal modal-bottom sm:modal-middle" aria-labelledby="{{ $id }}-title">
    <div class="modal-box rounded-2xl bg-base-100 {{ $size }} max-h-[90vh]">
        <h3 id="{{ $id }}-title" class="font-extrabold text-lg text-base-content mb-4">{{ $title }}</h3>

        {{ $slot }}
    </div>
    <form method="dialog" class="modal-backdrop"><button aria-label="Tutup">tutup</button></form>
</dialog>
