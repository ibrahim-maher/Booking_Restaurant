<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Display a listing of the current user's bookings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $bookings = $user->bookings()
                ->orderBy('date', 'desc')
                ->orderBy('time', 'desc')
                ->paginate(10);

            return response()->json([
                'bookings' => $bookings
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user bookings: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching bookings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Store a newly created booking
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
                'time' => 'required|date_format:H:i',
                'guests' => 'required|integer|min:1|max:20',
                'special_requests' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check availability
            if (!$this->isTimeSlotAvailable($request->date, $request->time, $request->guests)) {
                return response()->json([
                    'message' => 'The selected time slot is not available',
                ], 400);
            }

            $booking = new Booking();
            $booking->user_id = $request->user()->id;
            $booking->date = $request->date;
            $booking->time = $request->time;
            $booking->guests = $request->guests;
            $booking->special_requests = $request->special_requests;
            $booking->status = 'pending'; // Default status
            $booking->save();

            // Send notification to admin about new booking
            $this->createAdminNotification(
                'New booking created',
                'A new booking has been created and requires your attention.',
                $booking
            );

            // Send confirmation email to user
            $this->sendBookingConfirmationEmail($booking);

            return response()->json([
                'message' => 'Booking created successfully',
                'booking' => $booking
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating booking: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error creating booking',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Display the specified booking
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        try {
            $user = $request->user();
            $booking = Booking::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$booking) {
                return response()->json([
                    'message' => 'Booking not found'
                ], 404);
            }

            return response()->json([
                'booking' => $booking
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching booking: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching booking',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Update the specified booking
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        try {
            $user = $request->user();
            $booking = Booking::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$booking) {
                return response()->json([
                    'message' => 'Booking not found'
                ], 404);
            }

            // Don't allow updates for bookings that are not pending
            if ($booking->status !== 'pending') {
                return response()->json([
                    'message' => 'Only pending bookings can be updated'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'date' => 'sometimes|required|date|date_format:Y-m-d|after_or_equal:today',
                'time' => 'sometimes|required|date_format:H:i',
                'guests' => 'sometimes|required|integer|min:1|max:20',
                'special_requests' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check availability if date or time changed
            if (($request->has('date') && $request->date != $booking->date) || 
                ($request->has('time') && $request->time != $booking->time)) {
                $date = $request->date ?? $booking->date;
                $time = $request->time ?? $booking->time;
                $guests = $request->guests ?? $booking->guests;
                
                if (!$this->isTimeSlotAvailable($date, $time, $guests, $booking->id)) {
                    return response()->json([
                        'message' => 'The selected time slot is not available',
                    ], 400);
                }
            }

            // Update booking
            if ($request->has('date')) $booking->date = $request->date;
            if ($request->has('time')) $booking->time = $request->time;
            if ($request->has('guests')) $booking->guests = $request->guests;
            if ($request->has('special_requests')) $booking->special_requests = $request->special_requests;
            
            $booking->save();

            // Send notification about updated booking
            $this->createAdminNotification(
                'Booking updated',
                'A booking has been updated and requires your attention.',
                $booking
            );

            // Send email to user about updated booking
            $this->sendBookingUpdateEmail($booking);

            return response()->json([
                'message' => 'Booking updated successfully',
                'booking' => $booking
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating booking: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating booking',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Remove the specified booking
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        try {
            $user = $request->user();
            $booking = Booking::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$booking) {
                return response()->json([
                    'message' => 'Booking not found'
                ], 404);
            }

            // Don't allow cancellation for past bookings
            $now = Carbon::now();
            $bookingDateTime = Carbon::parse($booking->date . ' ' . $booking->time);
            
            if ($bookingDateTime->isPast()) {
                return response()->json([
                    'message' => 'Cannot cancel past bookings'
                ], 400);
            }

            // Add cancellation logic here
            $booking->status = 'cancelled';
            $booking->save();
            
            // Send notification about cancelled booking
            $this->createAdminNotification(
                'Booking cancelled',
                'A booking has been cancelled by the user.',
                $booking
            );

            // Send email to user confirming cancellation
            $this->sendBookingCancellationEmail($booking);

            return response()->json([
                'message' => 'Booking cancelled successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error cancelling booking: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error cancelling booking',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Check if a time slot is available
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAvailability(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
                'time' => 'required|date_format:H:i',
                'guests' => 'required|integer|min:1|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $available = $this->isTimeSlotAvailable($request->date, $request->time, $request->guests);

            return response()->json([
                'available' => $available
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error checking availability: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error checking availability',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get available time slots for a specific date
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableTimeSlots(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
                'guests' => 'required|integer|min:1|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Define restaurant hours
            $openingTime = Carbon::parse($request->date . ' 10:00');
            $closingTime = Carbon::parse($request->date . ' 22:00');
            
            // Generate time slots every 30 minutes
            $slots = [];
            $currentTime = clone $openingTime;
            
            while ($currentTime < $closingTime) {
                $timeString = $currentTime->format('H:i');
                $slots[] = [
                    'time' => $timeString,
                    'available' => $this->isTimeSlotAvailable($request->date, $timeString, $request->guests)
                ];
                $currentTime->addMinutes(30);
            }

            return response()->json([
                'date' => $request->date,
                'guests' => $request->guests,
                'time_slots' => $slots
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting available time slots: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error getting available time slots',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Display a listing of all bookings (admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminIndex(Request $request)
    {
        try {
            // Apply filters if provided
            $query = Booking::query()->with('user');

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date')) {
                $query->whereDate('date', $request->date);
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Sort by date and time
            $query->orderBy('date', 'desc')->orderBy('time', 'desc');

            $bookings = $query->paginate(15);

            return response()->json([
                'bookings' => $bookings
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching admin bookings: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching bookings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Display the specified booking for admin
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminShow($id)
    {
        try {
            $booking = Booking::with('user')->find($id);

            if (!$booking) {
                return response()->json([
                    'message' => 'Booking not found'
                ], 404);
            }

            return response()->json([
                'booking' => $booking
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching admin booking: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching booking',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Update booking status (admin only)
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus($id, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:pending,confirmed,completed,cancelled,rejected'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $booking = Booking::find($id);

            if (!$booking) {
                return response()->json([
                    'message' => 'Booking not found'
                ], 404);
            }

            $oldStatus = $booking->status;
            $booking->status = $request->status;
            $booking->save();

            // Send notification to user about status change
            $this->createUserNotification(
                $booking->user_id,
                'Booking status updated',
                'Your booking status has been updated to ' . $request->status,
                $booking
            );

            // Send status update email to user
            $this->sendStatusUpdateEmail($booking, $oldStatus);

            return response()->json([
                'message' => 'Booking status updated successfully',
                'booking' => $booking
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating booking status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating booking status',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get bookings for calendar view (admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCalendarView(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date|date_format:Y-m-d',
                'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bookings = Booking::whereBetween('date', [$request->start_date, $request->end_date])
                ->orderBy('date')
                ->orderBy('time')
                ->get();

            $calendarData = [];
            foreach ($bookings as $booking) {
                $calendarData[] = [
                    'id' => $booking->id,
                    'title' => $booking->guests . ' guests - ' . $booking->user->name,
                    'start' => $booking->date . 'T' . $booking->time,
                    'end' => $this->calculateEndTime($booking->date, $booking->time),
                    'status' => $booking->status,
                ];
            }

            return response()->json([
                'events' => $calendarData
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching calendar data: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching calendar data',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get bookings for a specific date (admin only)
     *
     * @param string $date
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBookingsByDate($date)
    {
        try {
            if (!Carbon::createFromFormat('Y-m-d', $date)) {
                return response()->json([
                    'message' => 'Invalid date format. Use YYYY-MM-DD.'
                ], 400);
            }

            $bookings = Booking::with('user')
                ->whereDate('date', $date)
                ->orderBy('time')
                ->get();

            return response()->json([
                'date' => $date,
                'bookings' => $bookings,
                'total' => $bookings->count(),
                'total_guests' => $bookings->sum('guests')
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching bookings by date: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching bookings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get booking statistics (admin only)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBookingStats(Request $request)
    {
        try {
            // Default to current month
            $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = $request->end_date ?? Carbon::now()->endOfMonth()->format('Y-m-d');
            
            // Validate dates if provided
            if ($request->has('start_date') || $request->has('end_date')) {
                $validator = Validator::make($request->all(), [
                    'start_date' => 'date|date_format:Y-m-d',
                    'end_date' => 'date|date_format:Y-m-d|after_or_equal:start_date',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }
            }
            
            // Get all relevant bookings
            $bookings = Booking::whereBetween('date', [$startDate, $endDate])->get();
            
            // Calculate statistics
            $totalBookings = $bookings->count();
            $confirmedBookings = $bookings->where('status', 'confirmed')->count();
            $pendingBookings = $bookings->where('status', 'pending')->count();
            $cancelledBookings = $bookings->where('status', 'cancelled')->count();
            $totalGuests = $bookings->sum('guests');
            
            // Get bookings by day of week
            $bookingsByDayOfWeek = [0, 0, 0, 0, 0, 0, 0]; // Sun to Sat
            foreach ($bookings as $booking) {
                $dayOfWeek = Carbon::parse($booking->date)->dayOfWeek;
                $bookingsByDayOfWeek[$dayOfWeek]++;
            }
            
            return response()->json([
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'total_bookings' => $totalBookings,
                'confirmed_bookings' => $confirmedBookings,
                'pending_bookings' => $pendingBookings,
                'cancelled_bookings' => $cancelledBookings,
                'total_guests' => $totalGuests,
                'average_party_size' => $totalBookings > 0 ? round($totalGuests / $totalBookings, 1) : 0,
                'bookings_by_day_of_week' => [
                    'sunday' => $bookingsByDayOfWeek[0],
                    'monday' => $bookingsByDayOfWeek[1],
                    'tuesday' => $bookingsByDayOfWeek[2],
                    'wednesday' => $bookingsByDayOfWeek[3],
                    'thursday' => $bookingsByDayOfWeek[4],
                    'friday' => $bookingsByDayOfWeek[5],
                    'saturday' => $bookingsByDayOfWeek[6]
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting booking statistics: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error getting booking statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Helper method to check if a time slot is available
     *
     * @param string $date
     * @param string $time
     * @param int $guests
     * @param int|null $excludeBookingId
     * @return bool
     */
    private function isTimeSlotAvailable($date, $time, $guests, $excludeBookingId = null)
    {
        // Convert request time to Carbon instance
        $requestTime = Carbon::parse($date . ' ' . $time);
        
        // Define restaurant constraints
        $maxCapacity = 50; // Maximum number of guests at any time
        $bookingDuration = 90; // Minutes per booking
        $openingTime = Carbon::parse($date . ' 10:00');
        $closingTime = Carbon::parse($date . ' 22:00');
        
        // Check if requested time is within opening hours
        if ($requestTime < $openingTime || $requestTime > $closingTime) {
            return false;
        }
        
        // Calculate time window for the booking
        $endTime = (clone $requestTime)->addMinutes($bookingDuration);
        
        // Get all bookings that overlap with the requested time window
        $query = Booking::where('date', $date)
            ->where('status', '!=', 'cancelled')
            ->where(function($query) use ($time, $endTime) {
                // Find bookings that overlap with the requested time slot
                $query->where(function($q) use ($time, $endTime) {
                    $bookingEndTime = Carbon::parse($time)->addMinutes(90)->format('H:i');
                    $q->where('time', '<=', $time)
                      ->where(DB::raw("ADDTIME(time, '01:30:00')"), '>', $time);
                })->orWhere(function($q) use ($time, $endTime) {
                    $q->where('time', '<', $endTime->format('H:i'))
                      ->where('time', '>=', $time);
                });
            });
            
        // Exclude the current booking if we're updating
        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }
            
        $overlappingBookings = $query->get();
        
        // Calculate total guests during the requested time
        $totalGuests = $overlappingBookings->sum('guests');
        
        // Check if adding these guests would exceed capacity
        return ($totalGuests + $guests) <= $maxCapacity;
    }

    /**
     * Helper method to calculate end time for calendar events
     *
     * @param string $date
     * @param string $time
     * @return string
     */
    private function calculateEndTime($date, $time)
    {
        // Assuming each booking lasts 90 minutes
        return Carbon::parse($date . 'T' . $time)->addMinutes(90)->format('Y-m-d\TH:i:s');
    }

    /**
     * Create notification for admin
     *
     * @param string $title
     * @param string $message
     * @param Booking $booking
     * @return void
     */
    private function createAdminNotification($title, $message, $booking)
    {
        try {
            // Find admin users
            $adminUsers = User::where('role', 'admin')->get();
            
            foreach ($adminUsers as $admin) {
                $notification = new Notification();
                $notification->user_id = $admin->id;
                $notification->title = $title;
                $notification->message = $message;
                $notification->type = 'booking';
                $notification->reference_id = $booking->id;
                $notification->is_read = false;
                $notification->save();
            }
        } catch (\Exception $e) {
            Log::error('Error creating admin notification: ' . $e->getMessage());
        }
    }

    /**
     * Create notification for user
     *
     * @param int $userId
     * @param string $title
     * @param string $message
     * @param Booking $booking
     * @return void
     */
    private function createUserNotification($userId, $title, $message, $booking)
    {
        try {
            $notification = new Notification();
            $notification->user_id = $userId;
            $notification->title = $title;
            $notification->message = $message;
            $notification->type = 'booking';
            $notification->reference_id = $booking->id;
            $notification->is_read = false;
            $notification->save();
        } catch (\Exception $e) {
            Log::error('Error creating user notification: ' . $e->getMessage());
        }
    }

    /**
     * Send booking confirmation email
     *
     * @param Booking $booking
     * @return void
     */
    private function sendBookingConfirmationEmail($booking)
    {
        try {
            $user = User::find($booking->user_id);
            
            // Here you would typically use Laravel's Mail facade
            // For now we'll just log the email
            Log::info("Sending booking confirmation email to {$user->email} for booking #{$booking->id}");
            
            // In a real implementation, you would do something like:
            // Mail::to($user->email)->send(new BookingConfirmation($booking));
        } catch (\Exception $e) {
            Log::error('Error sending booking confirmation email: ' . $e->getMessage());
        }
    }

    /**
     * Send booking update email
     *
     * @param Booking $booking
     * @return void
     */
    private function sendBookingUpdateEmail($booking)
    {
        try {
            $user = User::find($booking->user_id);
            
            // Log the email send
            Log::info("Sending booking update email to {$user->email} for booking #{$booking->id}");
            
            // In a real implementation:
            // Mail::to($user->email)->send(new BookingUpdated($booking));
        } catch (\Exception $e) {
            Log::error('Error sending booking update email: ' . $e->getMessage());
        }
    }

    /**
     * Send booking cancellation email
     *
     * @param Booking $booking
     * @return void
     */
    private function sendBookingCancellationEmail($booking)
    {
        try {
            $user = User::find($booking->user_id);
            
            // Log the email send
            Log::info("Sending booking cancellation email to {$user->email} for booking #{$booking->id}");
            
            // In a real implementation:
            // Mail::to($user->email)->send(new BookingCancelled($booking));
        } catch (\Exception $e) {
            Log::error('Error sending booking cancellation email: ' . $e->getMessage());
        }
    }

    /**
     * Send status update email
     *
     * @param Booking $booking
     * @param string $oldStatus
     * @return void
     */
    private function sendStatusUpdateEmail($booking, $oldStatus)
    {
        try {
            $user = User::find($booking->user_id);

            // Log the email send
            Log::info("Sending status update email to {$user->email} for booking #{$booking->id}. Old status: {$oldStatus}, New status: {$booking->status}");

            // In a real implementation:
            // Mail::to($user->email)->send(new BookingStatusUpdated($booking, $oldStatus));
        } catch (\Exception $e) {
            Log::error('Error sending status update email: ' . $e->getMessage());
        }
    }
}