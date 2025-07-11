<?php

namespace App\Services;

class GeofencingService
{
    /**
     * Calculate distance between two coordinates using Haversine formula
     * 
     * @param float $lat1 Latitude 1
     * @param float $lon1 Longitude 1
     * @param float $lat2 Latitude 2
     * @param float $lon2 Longitude 2
     * @return float Distance in meters
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Earth radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if coordinates are within geofence
     * 
     * @param float $userLat User latitude
     * @param float $userLon User longitude
     * @param float $officeLat Office latitude
     * @param float $officeLon Office longitude
     * @param int $radiusMeters Geofence radius in meters
     * @return array ['is_within' => bool, 'distance' => float]
     */
    public static function isWithinGeofence(
        float $userLat, 
        float $userLon, 
        float $officeLat, 
        float $officeLon, 
        int $radiusMeters
    ): array {
        $distance = self::calculateDistance($userLat, $userLon, $officeLat, $officeLon);
        
        return [
            'is_within' => $distance <= $radiusMeters,
            'distance' => round($distance, 2)
        ];
    }

    /**
     * Validate attendance location
     * 
     * @param \App\Models\Tenant $tenant
     * @param float $userLat
     * @param float $userLon
     * @return array
     */
    public static function validateAttendanceLocation($tenant, float $userLat, float $userLon): array
    {
        // If geofencing is not enforced, allow all locations
        if (!$tenant->enforce_geofencing || !$tenant->office_latitude || !$tenant->office_longitude) {
            return [
                'is_valid' => true,
                'is_within_geofence' => true,
                'distance' => 0,
                'message' => 'Geofencing not enforced'
            ];
        }

        $result = self::isWithinGeofence(
            $userLat,
            $userLon,
            $tenant->office_latitude,
            $tenant->office_longitude,
            $tenant->geofence_radius_meters
        );

        return [
            'is_valid' => $result['is_within'],
            'is_within_geofence' => $result['is_within'],
            'distance' => $result['distance'],
            'message' => $result['is_within'] 
                ? 'Location valid' 
                : "You are {$result['distance']}m away from office. Maximum allowed: {$tenant->geofence_radius_meters}m"
        ];
    }
}