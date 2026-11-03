<div>
    @if($chatOpen)
        <div class="chat-backdrop" wire:click.self="closeChat">
            <section class="chat-panel" role="dialog" aria-modal="true" aria-label="Sino — {{ $scope['label'] }}">
                <header class="chat-header">
                    <div class="chat-heading-icon">
                        <img class="sino-chat-logo" src="{{ asset('sino.png') }}" alt="Sino" onerror="this.style.display='none'" />
                    </div>
                    <div class="chat-heading-copy">
                        <strong>Sino</strong>
                        <span>{{ $scope['label'] }}</span>
                    </div>
                    <button type="button" class="chat-close" wire:click="closeChat" aria-label="Tutup chatbot">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </header>

                <div class="chat-suggestions">
                    @foreach($scope['suggestions'] as $suggestion)
                        <button type="button" wire:click="setSuggestion(@js($suggestion))">{{ $suggestion }}</button>
                    @endforeach
                </div>

                <div class="chat-messages">
                    @foreach($chatMessages as $index => $item)
                        <div wire:key="chat-msg-{{ $index }}" class="chat-message {{ $item['role'] === 'user' ? 'from-user' : 'from-ai' }}">
                            <div class="chat-message-content">
                                @if($item['role'] === 'assistant' || $item['role'] === 'ai')
                                    <div class="chat-markdown">{!! \Illuminate\Support\Str::markdown($item['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                                @else
                                    {{ $item['content'] }}
                                @endif
                            </div>
                            @if(!empty($item['source']))
                                <small>Sumber data: {{ $item['source'] }}</small>
                            @endif
                        </div>
                    @endforeach

                    <div wire:loading wire:target="sendChatMessage" class="chat-message from-ai chat-thinking" role="status" aria-label="Sino sedang menyusun jawaban">
                        <div class="chat-skeleton-bubble">
                            <span class="skeleton" style="width: 125px; height: 12px;"></span>
                            <div class="chat-skeleton-lines">
                                <span class="skeleton" style="width: 100%; height: 11px;"></span>
                                <span class="skeleton" style="width: 92%; height: 11px;"></span>
                                <span class="skeleton" style="width: 68%; height: 11px;"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <form class="chat-composer" wire:submit="sendChatMessage">
                    <input type="text" wire:model="chatInput" maxlength="2000" placeholder="Tanyakan tentang {{ $scope['label'] }}…" aria-label="Pertanyaan untuk chatbot" wire:loading.attr="disabled" wire:target="sendChatMessage" />
                    <button type="submit" wire:loading.attr="disabled" wire:target="sendChatMessage" aria-label="Kirim pertanyaan">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    </button>
                </form>
                @error('chatInput')<p class="chat-disclaimer" role="alert">{{ $message }}</p>@enderror
                <p class="chat-disclaimer">Jawaban AI perlu diverifikasi untuk keputusan operasional penting.</p>
            </section>
        </div>
    @endif
</div>
