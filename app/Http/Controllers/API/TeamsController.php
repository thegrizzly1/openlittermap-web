<?php

namespace App\Http\Controllers\API;

use App\Actions\Teams\CreateTeamAction;
use App\Actions\Teams\JoinTeamAction;
use App\Actions\Teams\LeaveTeamAction;
use App\Actions\Teams\UpdateTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamRequest;
use App\Http\Requests\Teams\JoinTeamRequest;
use App\Http\Requests\Teams\LeaveTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\User\User;
use Illuminate\Support\Facades\Auth;

class TeamsController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api')->except('types');
    }

    /**
     * Array of teams the user has joined
     *
     * @return array
     */
    public function list()
    {
        /** @var User $user */
        $user = Auth::user();

        return $this->success(['teams' => $user->teams]);
    }

    /**
     * The user wants to create a new team
     *
     * @param CreateTeamRequest $request
     * @param CreateTeamAction $action
     * @return array
     */
    public function create(CreateTeamRequest $request, CreateTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->remaining_teams === 0) {
            return $this->fail('max-teams-created');
        }

        $team = $action->run($user, $request->all());

        return $this->success(['team' => $team]);
    }

    /**
     * The user wants to update a team
     *
     * @param UpdateTeamRequest $request
     * @param UpdateTeamAction $action
     * @param Team $team
     * @return array
     */
    public function update(UpdateTeamRequest $request, UpdateTeamAction $action, Team $team)
    {
        if (Auth::id() != $team->leader) {
            return $this->fail('member-not-allowed');
        }

        $team = $action->run($team, $request->all());

        return $this->success(['team' => $team]);
    }

    /**
     * The user wants to join a team
     *
     * @param JoinTeamRequest $request
     * @param JoinTeamAction $action
     * @return array
     */
    public function join(JoinTeamRequest $request, JoinTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Team $team */
        $team = Team::whereIdentifier($request->identifier)->first();

        // Check the user is not already in the team
        if ($user->teams()->whereTeamId($team->id)->exists()) {
            return $this->fail('already-a-member');
        }

        $action->run($user, $team);

        return $this->success([
            'team' => $team->fresh(),
            'activeTeam' => $user->fresh()->team()->first()
        ]);
    }

    /**
     * The user wants to leave a team
     *
     * @param LeaveTeamRequest $request
     * @param LeaveTeamAction $action
     * @return array
     */
    public function leave(LeaveTeamRequest $request, LeaveTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Team $team */
        $team = Team::find($request->team_id);

        if (!$user->teams()->whereTeamId($request->team_id)->exists()) {
            return $this->fail('not-a-member');
        }

        if ($team->users()->count() <= 1) {
            return $this->fail('you-are-last-member');
        }

        $action->run($user, $team);

        return $this->success([
            'team' => $team->fresh(),
            'activeTeam' => $user->fresh()->team()->first()
        ]);
    }

    /**
     * Return the types of available teams
     *
     * @return array
     */
    public function types()
    {
        return $this->success([
            'types' => TeamType::select('id', 'team')->get()
        ]);
    }

    /**
     * Helper to output successful responses
     * @param array $data
     * @return array
     */
    private function success(array $data = []): array
    {
        return array_merge(['success' => true], $data);
    }

    /**
     * Helper to output error responses
     *
     * @param string $message
     * @return array
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}
