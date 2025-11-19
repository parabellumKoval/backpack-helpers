@foreach($rows as $row)
    <div class="seo-status-row rounded mb-1 px-3 py-2 {{ $row['container_class'] }}">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="d-flex align-items-baseline mr-3">
                <strong class="text-uppercase" style="font-size: 12px; font-weight: 400;">{{ $row['label'] }}</strong>
                <small class="ml-2 {{ $row['summary_class'] }}">
                    {{ $row['summary'] }}
                </small>
            </div>
            <div class="d-flex flex-wrap justify-content-end mt-2 mt-sm-0">
                @foreach($row['badges'] as $badge)
                    <span class="badge badge-pill text-uppercase small {{ $badge['filled'] ? 'badge-success' : 'badge-secondary' }} ml-1 mb-1 px-2 py-1">
                        {{ $badge['code'] }}
                    </span>
                @endforeach
            </div>
        </div>
        <div class="progress mt-1" style="height: 3px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $row['progress_percent'] }}%;" aria-valuenow="{{ $row['progress_percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>
@endforeach
