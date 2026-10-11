<?php

namespace App\Http\Controllers;

use App\Constants\APIResponseCodes;
use App\Http\Requests\CheckInChildRequest;
use App\Http\Resources\CheckInResource;
use App\Models\Room;
use App\Services\CheckInService;
use App\Services\CheckOutService;

class CheckInController extends Controller
{
    public function store(CheckInChildRequest $request, Room $room, CheckInService $service)
    {
        $checkIn = $service->checkIn($room, $request->validated('child_id'));

        return $this->success(APIResponseCodes::CREATED, new CheckInResource($checkIn));
    }

    public function checkOut(string $checkIn, CheckOutService $service)
    {
        return $this->success(APIResponseCodes::SUCCESS, new CheckInResource($service->checkOut($checkIn)));
    }
}
