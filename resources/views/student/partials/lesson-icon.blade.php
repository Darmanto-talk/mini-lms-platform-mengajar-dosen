@php $name = $name ?? 'doc'; @endphp
@switch($name)
  @case('video')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="15" height="14" rx="2.5"/><path d="m17 9 5-3v12l-5-3"/></svg>
    @break
  @case('quiz')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.1 9a3 3 0 1 1 4 2.8c-.9.4-1.6 1.3-1.6 2.2v.5"/><line x1="12" y1="18" x2="12.01" y2="18"/><circle cx="12" cy="12" r="9.5"/></svg>
    @break
  @case('task')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1Z"/><rect x="5" y="6" width="14" height="15" rx="2"/><path d="m9 13 2 2 4-4"/></svg>
    @break
  @case('code')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 8 5 12 9 16"/><polyline points="15 8 19 12 15 16"/></svg>
    @break
  @default
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v6h6"/></svg>
@endswitch
