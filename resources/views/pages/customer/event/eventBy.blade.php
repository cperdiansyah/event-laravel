<x-app-costumer-layout>

  <section class="container">
    <div class='mt-16'>

      @if ($user)
        <h3 class='font-semibold text-slate-500'>Penyelenggara Event : {{ $user->nama_user }} </h3>
      @endif

      @if ($events)
        <div class="container">
          <div class="flex flex-row flex-wrap pb-5 pt-3">
            @foreach ($events as $event)
              
              @include('pages.customer.event.eventCard')
            @endforeach

          </div>
        </div>
      @endif

    </div>

  </section>

</x-app-costumer-layout>
