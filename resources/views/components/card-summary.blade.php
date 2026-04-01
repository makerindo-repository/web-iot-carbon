@props(['href' => '#', 'title' => '', 'total' => 10, 'icon' => 'fa-globe'])
<a href="{{ $href }}" class="h-full">
    <div class="bg-white flex justify-between items-center px-4 py-2 rounded-lg h-full">
        <div class="flex flex-col gap-4 h-100">
            <h3 class="text-base font-medium">{{ $title }}</h3>
            <p class="text-base font-semibold">{{ $total }}</p>
        </div>
        <div class="bg-primary text-white rounded-full flex items-center justify-center p-3">
            <i class="fa-solid {{ $icon }}"></i>
        </div>
    </div>
</a>
