<h2 class="h5 mb-3 d-flex align-items-center">
                    <small style="font-size:10px">έχετε επιλέξει: </small>
                    @php
                        $from = $filters['from'] ?? null;
                        $to   = $filters['to']   ?? null;
                    @endphp

                    @if($from || $to)
                        <span class="badge text-dark border ms-2" style="background: rgb(169 169 169)">
                            @if($from && $to && $from === $to)
                                Ημερομηνία:
                                {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}
                            @elseif($from && $to)
                                Από
                                {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}
                                έως
                                {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
                            @elseif($from)
                                Από
                                {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}
                            @else
                                Έως
                                {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
                            @endif
                        </span>
                    @endif
                </h2>