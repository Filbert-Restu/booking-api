<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\Request;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Http\Requests\Room\CheckAvailabilityRequest;
use App\Http\Requests\Room\ScheduleRoomRequest;
use App\Http\Requests\Room\UploadRoomImageRequest;
use App\Http\Requests\Room\DeleteRoomImageRequest;

class RoomController extends Controller
{
    protected RoomService $roomService;

    public function __construct(RoomService $roomService)
    {
        $this->roomService = $roomService;
    }

    public function index(Request $request)
    {
        $rooms = $this->roomService->listRooms($request->all());

        return response()->json([
            'success' => true,
            'data' => $rooms,
        ]);
    }

    public function show(Request $request, $id)
    {
        $data = $this->roomService->showRoom($id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(StoreRoomRequest $request)
    {
        try {
            $imageFiles = $request->hasFile('images') ? $request->file('images') : [];
            $room = $this->roomService->createRoom($request->validated(), $imageFiles);

            return response()->json([
                'success' => true,
                'message' => 'Ruangan berhasil dibuat',
                'data' => $room,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat ruangan. Silakan coba lagi.',
            ], 500);
        }
    }

    public function update(UpdateRoomRequest $request, $id)
    {
        $room = Room::findOrFail($id);
        $room = $this->roomService->updateRoom($room, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil diupdate',
            'data' => $room,
        ]);
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);

        try {
            $this->roomService->deleteRoom($room);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil dihapus',
        ]);
    }

    public function checkAvailability(CheckAvailabilityRequest $request, $id)
    {
        $room = Room::findOrFail($id);
        $data = $this->roomService->checkAvailability($room, $request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function schedule(ScheduleRoomRequest $request, $id)
    {
        $room = Room::select('id', 'name', 'code', 'capacity')->findOrFail($id);
        $data = $this->roomService->getSchedule($room, $request->start_date, $request->end_date);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function uploadImage(UploadRoomImageRequest $request, $id)
    {
        $room = Room::findOrFail($id);
        $result = $this->roomService->uploadImage($room, $request->file('image'));

        return response()->json([
            'success' => true,
            'message' => 'Foto berhasil diupload',
            'data' => [
                'path' => $result['path'],
                'url' => route('api.rooms.image', ['id' => $room->id, 'path' => urlencode($result['path'])]),
                'all_images' => $result['all_images'],
            ],
        ]);
    }

    public function deleteImage(DeleteRoomImageRequest $request, $id)
    {
        $room = Room::findOrFail($id);

        try {
            $result = $this->roomService->deleteImage($room, $request->path);

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil dihapus',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    public function serveImage(DeleteRoomImageRequest $request, $id)
    {
        $room = Room::findOrFail($id);

        try {
            $result = $this->roomService->serveImage($room, $request->path);

            return response($result['content'], 200, [
                'Content-Type' => $result['mimeType'],
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 404);
        }
    }
}
