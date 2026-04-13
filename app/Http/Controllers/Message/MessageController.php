<?php

namespace App\Http\Controllers\Message;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Events\MessageSent;
use App\Models\User;
use App\Models\UserNotificationSetting;
use App\Notifications\ClientMessageNotification;
use App\Notifications\CoachMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function sendMessage(Request $request)
    {


        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string'
        ]);

        $msg = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
        ]);

        $receiver = User::find($request->receiver_id);

        broadcast(new MessageSent($msg->load('sender')))->toOthers();

        //        return $receiver->notificationSettings;

        $notificationSettings = UserNotificationSetting::where('user_id',$request->receiver_id)->first();

        if($receiver->user_type == 'individual' && $notificationSettings && $notificationSettings->coach_messages == 1)
        {
            $receiver->notify(new CoachMessageNotification('new Coach Message',$request->message,'coach_message'));
        }
        else
        {
            if ($notificationSettings && $notificationSettings->client_messages == 1)
            {
                $receiver->notify(new ClientMessageNotification('new Client Message',$request->message,'client_message'));
            }
            else
            {

            }
        }

        return response()->json([
            'success' => true,
            'data' => $msg
        ]);
    }

    public function getMessages($id)
    {
        $messages = Message::where(function($q) use ($id) {
            $q->where('sender_id', Auth::id())->where('receiver_id', $id);
        })->orWhere(function($q) use ($id) {
            $q->where('sender_id', $id)->where('receiver_id', Auth::id());
        })
        ->with(['sender', 'receiver'])
        ->orderBy('created_at', 'asc')
        ->get();

        return response()->json($messages);
    }

    public function getConversations()
    {
        $userId = auth()->id();

        $conversations = Message::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($message) use ($userId) {
                return $message->sender_id == $userId ? $message->receiver_id : $message->sender_id;
            });

        return response()->json($conversations);
    }
}
