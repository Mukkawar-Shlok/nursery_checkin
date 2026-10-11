<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckInChildRequest;
use App\Http\Resources\CheckInResource;
use App\Models\Room;
use App\Services\CheckInService;
use App\Services\CheckOutService;

class CheckInController extends Controller
{
    public function store(CheckInChildRequest $request, Room $room, CheckInService $service)
    {
        return CheckInResource::make($service->checkIn($room, $request->validated('child_id')))
            ->response()
            ->setStatusCode(201);
    }

    public function checkOut(string $checkIn, CheckOutService $service)
    {
        return CheckInResource::make($service->checkOut($checkIn));
    }
}
