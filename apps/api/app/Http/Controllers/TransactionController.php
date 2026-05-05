<?php

namespace App\Http\Controllers;

use App\Models\Amusement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function create(User $user, Amusement $amusement, $amount, $type)
    {
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'amusement_id' => $amusement->id,
            'amount' => $amount,
            'type' => $type
        ]);

        return $transaction;
    }
}
