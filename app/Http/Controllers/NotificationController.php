<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Display a listing of the user's notifications
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            $query = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Filter by read status if requested
            if ($request->has('read')) {
                $query->where('read', $request->read === 'true');
            }

            $notifications = $query->paginate(15);

            return response()->json([
                'notifications' => $notifications,
                'unread_count' => Notification::where('user_id', $user->id)
                    ->where('read', false)
                    ->count()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching notifications: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Mark a notification as read
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead($id, Request $request)
    {
        try {
            $user = $request->user();
            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->read = true;
            $notification->save();

            return response()->json([
                'message' => 'Notification marked as read',
                'unread_count' => Notification::where('user_id', $user->id)
                    ->where('read', false)
                    ->count()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error marking notification as read',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Mark all user notifications as read
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = $request->user();
            
            Notification::where('user_id', $user->id)
                ->where('read', false)
                ->update(['read' => true]);

            return response()->json([
                'message' => 'All notifications marked as read',
                'unread_count' => 0
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error marking all notifications as read',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Send a notification (internal function)
     *
     * @param int $userId
     * @param string $type
     * @param string $message
     * @param array $data
     * @return Notification|null
     */
    public static function sendNotification($userId, $type, $message, $data = [])
    {
        try {
            $notification = new Notification();
            $notification->user_id = $userId;
            $notification->type = $type;
            $notification->message = $message;
            $notification->data = json_encode($data);
            $notification->read = false;
            $notification->save();

            return $notification;
        } catch (\Exception $e) {
            Log::error('Error sending notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send a booking notification
     *
     * @param int $userId
     * @param int $bookingId
     * @param string $status
     * @param string $message
     * @return Notification|null
     */
    public static function sendBookingNotification($userId, $bookingId, $status, $message)
    {
        $data = [
            'booking_id' => $bookingId,
            'status' => $status
        ];

        return self::sendNotification($userId, 'booking', $message, $data);
    }

    /**
     * Delete a notification (optional)
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        try {
            $user = $request->user();
            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'message' => 'Notification deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting notification: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting notification',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
}