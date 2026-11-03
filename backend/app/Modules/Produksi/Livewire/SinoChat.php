<?php

namespace App\Modules\Produksi\Livewire;

use App\Modules\Produksi\Services\SinoAiService;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;
use App\Services\SinoScopeService;
use Livewire\Attributes\Locked;

class SinoChat extends Component
{
    public bool $chatOpen = false;
    public string $chatInput = '';
    public array $chatMessages = [];
    #[Locked]
    public string $chatScope = '';

    public function boot(): void
    {
        Gate::authorize('use-sino');
    }

    public function mount(): void
    {
        $this->initializeChat();
    }

    public function hydrate(): void
    {
        if ($this->chatScope !== app(SinoScopeService::class)->current()['key']) {
            $this->initializeChat();
        }
    }

    protected function initializeChat(): void
    {
        $scope = app(SinoScopeService::class)->current();
        $this->chatScope = $scope['key'];
        $this->chatInput = '';
        $this->chatMessages = [
            [
                'role'    => 'assistant',
                'content' => 'Halo, saya Sino. Saya dapat membantu pertanyaan terkait '.$scope['label'].'.',
                'source'  => null,
            ],
        ];
    }

    #[On('open-sino-chat')]
    public function openChat(): void
    {
        $this->chatOpen = true;
    }

    public function closeChat(): void
    {
        $this->chatOpen = false;
    }

    public function setSuggestion(string $text): void
    {
        $this->chatInput = $text;
    }

    public function sendChatMessage(): void
    {
        $message = trim($this->chatInput);
        if ($message === '') {
            return;
        }
        $this->validate(['chatInput' => 'required|string|max:2000'], ['chatInput.max' => 'Pertanyaan maksimal 2.000 karakter.']);

        $this->chatInput = '';
        $this->chatMessages[] = [
            'role'    => 'user',
            'content' => $message,
            'source'  => null,
        ];

        $response = app(SinoAiService::class)->ask($message);

        $this->chatMessages[] = [
            'role'    => 'assistant',
            'content' => $response['answer'] ?? 'Maaf, belum ada jawaban dari asisten.',
            'source'  => $response['source'] ?? null,
        ];
    }

    public function render()
    {
        return view('modules.produksi.livewire.sino-chat', ['scope' => app(SinoScopeService::class)->current()]);
    }
}
