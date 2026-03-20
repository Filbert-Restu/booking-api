<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RoomService
{
    public function listRooms(array $filters)
    {
        $query = Room::query()
            ->select('id', 'name', 'code', 'capacity', 'status', 'facilities', 'description', 'images');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['capacity_min'])) {
            $query->where('capacity', '>=', $filters['capacity_min']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $rooms = $query->latest()->paginate($filters['per_page'] ?? 15);

        // Annotate availability if params provided
        if (isset($filters['available_date'], $filters['available_start'], $filters['available_end'])) {
            $rooms->getCollection()->transform(function ($room) use ($filters) {
                $room->is_available = $room->isAvailable(
                    $filters['available_date'],
                    $filters['available_start'],
                    $filters['available_end']
                );
                return $room;
            });
        }

        return $rooms;
    }

    public function showRoom(int $id): array
    {
        $room = Room::select('id', 'name', 'code', 'capacity', 'status', 'facilities', 'description', 'images')
            ->findOrFail($id);

        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(7);

        $upcomingBookings = RoomBooking::with([
                'document:id,title,status',
                'bookedBy:id,name,email'
            ])
            ->select('id', 'document_id', 'room_id', 'booked_by', 'booking_date', 'start_time', 'end_time', 'purpose', 'status')
            ->where('room_id', $id)
            ->whereBetween('booking_date', [$startDate, $endDate])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        return [
            'room' => $room,
            'upcoming_bookings' => $upcomingBookings,
        ];
    }

    public function createRoom(array $data, array $imageFiles = []): Room
    {
        $uploadedPaths = [];

        DB::beginTransaction();

        try {
            foreach ($imageFiles as $image) {
                $path = $image->store('rooms', 'private');
                $uploadedPaths[] = $path;
            }

            $data['images'] = $uploadedPaths;
            $room = Room::create($data);

            DB::commit();

            return $room;
        } catch (\Exception $e) {
            DB::rollBack();

            foreach ($uploadedPaths as $path) {
                if (Storage::disk('private')->exists($path)) {
                    Storage::disk('private')->delete($path);
                }
            }

            \Log::error('Room Store Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateRoom(Room $room, array $data): Room
    {
        $room->update($data);

        return $room;
    }

    public function deleteRoom(Room $room): void
    {
        if ($room->activeBookings()->exists()) {
            throw new \Exception('Tidak dapat menghapus ruangan yang masih memiliki booking aktif', 400);
        }

        $room->delete();
    }

    public function checkAvailability(Room $room, array $params): array
    {
        $excludeDocumentId = $params['exclude_document_id'] ?? null;

        \Log::debug('RoomService.checkAvailability()', [
            'room_id' => $room->id,
            'date' => $params['date'],
            'exclude_document_id' => $excludeDocumentId,
        ]);

        $isAvailable = $room->isAvailable(
            $params['date'],
            $params['start_time'],
            $params['end_time'],
            null,
            $excludeDocumentId
        );

        \Log::debug('Availability result', ['available' => $isAvailable]);

        $conflicts = null;
        if (!$isAvailable) {
            $conflictQuery = RoomBooking::with(['document', 'bookedBy'])
                ->where('room_id', $room->id)
                ->where('booking_date', $params['date'])
                ->where('start_time', '<', $params['end_time'])
                ->where('end_time', '>', $params['start_time']);

            if ($excludeDocumentId) {
                $conflictQuery->where('document_id', '!=', $excludeDocumentId);
            }

            $conflicts = $conflictQuery->get();

            \Log::debug('Conflicts found', ['count' => $conflicts->count()]);
        }

        return [
            'available' => $isAvailable,
            'room' => $room,
            'date' => $params['date'],
            'start_time' => $params['start_time'],
            'end_time' => $params['end_time'],
            'conflicts' => $conflicts,
        ];
    }

    public function getSchedule(Room $room, string $startDate, string $endDate): array
    {
        $bookings = RoomBooking::with([
                'document:id,title,status,content',
                'bookedBy:id,name,email,unit_id',
                'bookedBy.unit:id,name,code'
            ])
            ->select('id', 'document_id', 'room_id', 'booked_by', 'booking_date', 'start_time', 'end_time', 'purpose', 'status')
            ->where('room_id', $room->id)
            ->whereBetween('booking_date', [$startDate, $endDate])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($booking) {
                $data = $booking->toArray();
                $data['booked_by_user'] = $booking->bookedBy ? [
                    'id' => $booking->bookedBy->id,
                    'name' => $booking->bookedBy->name,
                    'email' => $booking->bookedBy->email,
                    'unit_code' => $booking->bookedBy->unit?->code,
                    'unit_name' => $booking->bookedBy->unit?->name,
                ] : null;
                return $data;
            });

        return [
            'room' => $room,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'bookings' => $bookings,
        ];
    }

    public function uploadImage(Room $room, UploadedFile $image): array
    {
        $path = $image->store('rooms', 'private');

        $images = $room->images ?? [];
        $images[] = $path;
        $room->images = $images;
        $room->save();

        return [
            'path' => $path,
            'all_images' => $room->images,
        ];
    }

    public function deleteImage(Room $room, string $path): array
    {
        $images = $room->images ?? [];

        if (!in_array($path, $images)) {
            throw new \Exception('Path gambar tidak valid untuk ruangan ini', 400);
        }

        if (Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }

        $images = array_values(array_filter($images, function ($img) use ($path) {
            return $img !== $path;
        }));

        $room->images = $images;
        $room->save();

        return ['all_images' => $room->images];
    }

    public function serveImage(Room $room, string $path): array
    {
        if (!in_array($path, $room->images ?? [])) {
            throw new \Exception('Image not found in room', 404);
        }

        if (!Storage::disk('private')->exists($path)) {
            throw new \Exception('File not found in storage', 404);
        }

        $fileContent = Storage::disk('private')->get($path);
        $mimeType = Storage::disk('private')->mimeType($path) ?: 'image/jpeg';

        return [
            'content' => $fileContent,
            'mimeType' => $mimeType,
        ];
    }
}
