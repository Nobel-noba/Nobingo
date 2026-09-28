<?php

namespace Tests\Feature;

use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Games\Events\GameStateChanged;
use App\Domains\Games\Events\NumberCalled;
use App\Domains\Games\Events\PlayerJoinedGame;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Games\Services\NumberCallingService;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealTimeEngineTest extends TestCase
{
    use RefreshDatabase;

    protected GameLifecycleService $lifecycleService;

    protected CardAssignmentService $assignmentService;

    protected NumberCallingService $callingService;

    protected BingoCardGenerator $cardGenerator;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            WinningPatternSeeder::class,
            GameTemplateSeeder::class,
        ]);

        $this->lifecycleService = new GameLifecycleService;
        $this->assignmentService = new CardAssignmentService;
        $this->callingService = new NumberCallingService;
        $this->cardGenerator = new BingoCardGenerator(new BingoCardValidator);

        $this->company = Company::where('slug', 'acme-bingo')->firstOrFail();
    }

    public function test_number_called_event_implements_broadcasting_channels_and_payload(): void
    {
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);

        $call = new GameCall([
            'game_id' => $game->id,
            'sequence_index' => 5,
            'ball_number' => 24,
            'letter' => 'I',
            'called_at' => now(),
        ]);

        $event = new NumberCalled($game, $call, 70, 3);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame("private-company.{$this->company->id}.game.{$game->id}", $channels[0]->name);

        $this->assertSame('number.called', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($game->id, $payload['game_id']);
        $this->assertSame(5, $payload['sequence_index']);
        $this->assertSame(24, $payload['ball_number']);
        $this->assertSame('I', $payload['letter']);
        $this->assertSame('I-24', $payload['code']);
        $this->assertSame(70, $payload['remaining_count']);
        $this->assertSame(3, $payload['marked_cards_count']);
    }

    public function test_game_state_changed_event_broadcasts_to_game_and_lobby_channels(): void
    {
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);

        $event = new GameStateChanged($game, 'draft');

        $channels = $event->broadcastOn();
        $this->assertCount(2, $channels);
        $this->assertSame("private-company.{$this->company->id}.game.{$game->id}", $channels[0]->name);
        $this->assertSame("private-company.{$this->company->id}.lobby", $channels[1]->name);

        $this->assertSame('game.state.changed', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($game->id, $payload['game_id']);
        $this->assertSame($game->status, $payload['status']);
        $this->assertSame('draft', $payload['previous_status']);
    }

    public function test_player_joined_event_broadcasts_to_game_and_lobby_channels(): void
    {
        $player = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Alice']);
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);

        $event = new PlayerJoinedGame($game, $player, 8);

        $channels = $event->broadcastOn();
        $this->assertCount(2, $channels);
        $this->assertSame("private-company.{$this->company->id}.game.{$game->id}", $channels[0]->name);
        $this->assertSame("private-company.{$this->company->id}.lobby", $channels[1]->name);

        $this->assertSame('player.joined', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($game->id, $payload['game_id']);
        $this->assertSame($player->id, $payload['user_id']);
        $this->assertSame('Alice', $payload['player_name']);
        $this->assertSame(8, $payload['players_count']);
    }

    public function test_lifecycle_transition_dispatches_game_state_changed_event(): void
    {
        Event::fake([GameStateChanged::class]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);

        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);

        Event::assertDispatched(GameStateChanged::class, function ($e) use ($game) {
            return $e->game->id === $game->id && $e->game->status === Game::STATUS_OPEN;
        });
    }

    public function test_joining_game_dispatches_player_joined_game_event(): void
    {
        Event::fake([PlayerJoinedGame::class]);

        $this->cardGenerator->generateBatch($this->company, 5);
        $player = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);

        $this->assignmentService->joinGame($game, $player);

        Event::assertDispatched(PlayerJoinedGame::class, function ($e) use ($game, $player) {
            return $e->game->id === $game->id && $e->user->id === $player->id && $e->playersCount === 1;
        });
    }

    public function test_calling_number_dispatches_number_called_event(): void
    {
        Event::fake([NumberCalled::class]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        $this->callingService->callNextNumber($game);

        Event::assertDispatched(NumberCalled::class, function ($e) use ($game) {
            return $e->game->id === $game->id && $e->call->sequence_index === 1;
        });
    }

    public function test_channel_authorization_enforces_tenant_and_game_isolation(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app-id',
        ]);
        require base_path('routes/channels.php');

        $companyB = Company::where('slug', 'lucky-star')->firstOrFail();

        $owner = User::where('email', 'owner@nobingo.test')->firstOrFail();
        $adminA = User::where('email', 'admin@acme.test')->firstOrFail();
        $playerA = User::where('email', 'player1@acme.test')->firstOrFail();
        $playerB = User::where('email', 'player2@luckystar.test')->firstOrFail();

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $gameA = $this->lifecycleService->createFromTemplate($template, $this->company);

        // Platform owner is always authorized
        $channelName = "private-company.{$this->company->id}.game.{$gameA->id}";
        $resp = $this->actingAs($owner)
            ->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.5678']);
        $resp->assertOk();

        // Company admin of company is authorized
        $this->actingAs($adminA)
            ->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.5678'])
            ->assertOk();

        // Player belonging to company is authorized
        $this->actingAs($playerA)
            ->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.5678'])
            ->assertOk();

        // Player belonging to Company B is REJECTED
        $this->actingAs($playerB)
            ->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.5678'])
            ->assertForbidden();

        // Lobby channel authorization: Company A player can access Company A lobby, but not Company B lobby
        $lobbyChannelA = "private-company.{$this->company->id}.lobby";
        $lobbyChannelB = "private-company.{$companyB->id}.lobby";

        $this->actingAs($playerA)
            ->postJson('/broadcasting/auth', ['channel_name' => $lobbyChannelA, 'socket_id' => '1234.5678'])
            ->assertOk();

        $this->actingAs($playerA)
            ->postJson('/broadcasting/auth', ['channel_name' => $lobbyChannelB, 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }
}
