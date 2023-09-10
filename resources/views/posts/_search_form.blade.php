<form action="{{ route('home') }}" method="GET" class="d-flex" data-turbo="true" data-turbo-frame="posts" data-turbo-action="advance">
  @csrf

  <div class="input-group me-sm-3">
    <input
        type="text"
        id="q"
        name="q"
        class="form-control"
        placeholder="@lang('posts.search')"
        value="{{ request('q') }}"
    >
  </div>

  <button type="submit" class="btn btn-primary">
    <x-icon name="magnifying-glass" />
  </button>
</form>
