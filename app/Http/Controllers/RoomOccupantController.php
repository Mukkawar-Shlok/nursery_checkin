<?php

namespace App\Http\Controllers;

use App\Constants\APIResponseCodes;
use App\Http\Resources\OccupantListResource;
use App\Models\Room;
use App\Services\OccupantService;
use Illuminate\Http\Request;

class RoomOccupantController extends Controller
{
    public function index(Request $request, Room $room, OccupantService $service)
    {
        $occupants = $service->forRoom($room, $request->integer('per_page', 25));

        return $this->success(APIResponseCodes::SUCCESS, new OccupantListResource($occupants));
    }
}
