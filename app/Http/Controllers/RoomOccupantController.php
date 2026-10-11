<?php

namespace App\Http\Controllers;

use App\Http\Resources\OccupantResource;
use App\Models\Room;
use App\Services\OccupantService;
use Illuminate\Http\Request;

class RoomOccupantController extends Controller
{
    public function index(Request $request, Room $room, OccupantService $service)
    {
        return OccupantResource::collection($service->forRoom($room, $request->integer('per_page', 25)));
    }
}
