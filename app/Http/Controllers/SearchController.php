<?php

namespace App\Http\Controllers;

use App\Models\ClientEntity;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->input('q', ''));
        $results = [
            'users' => collect(),
            'proposals' => collect(),
            'clients' => collect(),
        ];

        if ($term !== '') {
            $results['users'] = User::query()->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->limit(8)->get();
            $results['proposals'] = Proposal::query()->where('proposal_code', 'like', "%{$term}%")->orWhere('client_name', 'like', "%{$term}%")->limit(8)->get();
            $results['clients'] = ClientEntity::query()->where('display_name', 'like', "%{$term}%")->orWhere('legal_name', 'like', "%{$term}%")->limit(8)->get();
        }

        return view('pages.admin.search', [
            'title' => 'Busca',
            'term' => $term,
            'results' => $results,
        ]);
    }
}
