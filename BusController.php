<?php

class BusController
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db->getConnection();
    }

    // Метод /api/find-bus
    
    public function findBus()
    {
        $from = isset($_GET['from']) ? (int)$_GET['from'] : 0;
        $to   = isset($_GET['to']) ? (int)$_GET['to'] : 0;

        if (!$from || !$to || $from === $to) {
            ResponseHelper::jsonResponse([
                "error" => "Некорректные параметры from/to"
            ], 400);
            return;
        }

        $sqlStops = "SELECT stop_id, name FROM stops WHERE stop_id IN (:from, :to)";
        $stmtStops = $this->db->prepare($sqlStops);
        $stmtStops->execute([':from' => $from, ':to' => $to]);
        $stopNames = $stmtStops->fetchAll(PDO::FETCH_ASSOC);

        $fromName = '';
        $toName   = '';
        foreach ($stopNames as $s) {
            if ($s['stop_id'] == $from) $fromName = $s['name'];
            if ($s['stop_id'] == $to)   $toName   = $s['name'];
        }

        $sql = "
            SELECT r.route_id, r.bus_number, r.final_stop
            FROM routes r
            JOIN route_stops rs_from ON rs_from.route_id = r.route_id AND rs_from.stop_id = :from
            JOIN route_stops rs_to   ON rs_to.route_id   = r.route_id AND rs_to.stop_id   = :to
            WHERE rs_from.position < rs_to.position
            ORDER BY r.route_id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':from' => $from,
            ':to'   => $to
        ]);
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultBuses = [];

        $currentTime = date('H:i:s'); 

        foreach ($routes as $route) {
            $sqlRsid = "
                SELECT route_stop_id 
                FROM route_stops 
                WHERE route_id = :route_id AND stop_id = :stop_id
            ";
            $stmtRsid = $this->db->prepare($sqlRsid);
            $stmtRsid->execute([':route_id' => $route['route_id'], ':stop_id' => $from]);
            $rowRsid = $stmtRsid->fetch(PDO::FETCH_ASSOC);
            if (!$rowRsid) {
                continue; 
            }
            $routeStopId = $rowRsid['route_stop_id'];

            $sqlTimes = "
                SELECT arrival_time
                FROM route_stop_times
                WHERE route_stop_id = :rsid
                  AND arrival_time >= :current_time
                ORDER BY arrival_time
                LIMIT 3
            ";
            $stmtTimes = $this->db->prepare($sqlTimes);
            $stmtTimes->execute([
                ':rsid'         => $routeStopId,
                ':current_time' => $currentTime
            ]);
            $times = $stmtTimes->fetchAll(PDO::FETCH_COLUMN);

            $formattedTimes = array_map(function($t) {
                return substr($t, 0, 5);
            }, $times);

            $resultBuses[] = [
                "route"         => $route['bus_number'] . " в сторону " . $route['final_stop'],
                "next_arrivals" => $formattedTimes
            ];
        }

        $response = [
            "from"  => $fromName,
            "to"    => $toName,
            "buses" => $resultBuses
        ];

        ResponseHelper::jsonResponse($response);
    }

    public function createOrUpdateRoute()
    {
        $rawBody = file_get_contents("php://input");
        $data = json_decode($rawBody, true);

        if (!isset($data['bus_number']) || !isset($data['final_stop']) || !isset($data['stops'])) {
            ResponseHelper::jsonResponse(["error" => "Недостаточно данных"], 400);
            return;
        }

        $busNumber = $data['bus_number'];
        $finalStop = $data['final_stop'];
        $stops     = $data['stops'];    
        $routeId   = isset($data['route_id']) ? (int)$data['route_id'] : 0;

        try {
            $this->db->beginTransaction();

            if ($routeId > 0) {
                $sqlUpdate = "UPDATE routes SET bus_number = :bus_number, final_stop = :final_stop WHERE route_id = :route_id";
                $stmt = $this->db->prepare($sqlUpdate);
                $stmt->execute([
                    ':bus_number' => $busNumber,
                    ':final_stop' => $finalStop,
                    ':route_id'   => $routeId
                ]);

                $sqlDeleteStops = "DELETE FROM route_stops WHERE route_id = :route_id";
                $stmtDel = $this->db->prepare($sqlDeleteStops);
                $stmtDel->execute([':route_id' => $routeId]);

            } else {
                $sqlInsert = "INSERT INTO routes (bus_number, final_stop) VALUES (:bus_number, :final_stop) RETURNING route_id";
                $stmt = $this->db->prepare($sqlInsert);
                $stmt->execute([
                    ':bus_number' => $busNumber,
                    ':final_stop' => $finalStop
                ]);
                $routeId = $stmt->fetchColumn(); 
            }

            $position = 1;
            foreach ($stops as $stopId) {
                $sqlInsertStop = "INSERT INTO route_stops (route_id, stop_id, position) VALUES (:route_id, :stop_id, :position)";
                $stmtIS = $this->db->prepare($sqlInsertStop);
                $stmtIS->execute([
                    ':route_id' => $routeId,
                    ':stop_id'  => $stopId,
                    ':position' => $position++
                ]);
            }

            $this->db->commit();

            ResponseHelper::jsonResponse([
                "success"  => true,
                "route_id" => $routeId
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            ResponseHelper::jsonResponse(["error" => $e->getMessage()], 500);
        }
    }

    public function deleteRoute()
    {
        $routeId = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;

        if ($routeId <= 0) {
            ResponseHelper::jsonResponse(["error" => "Не указан route_id"], 400);
            return;
        }

        try {
            $sql = "DELETE FROM routes WHERE route_id = :route_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':route_id' => $routeId]);

            if ($stmt->rowCount() > 0) {
                ResponseHelper::jsonResponse(["success" => true]);
            } else {
                ResponseHelper::jsonResponse(["error" => "Маршрут не найден"], 404);
            }
        } catch (Exception $e) {
            ResponseHelper::jsonResponse(["error" => $e->getMessage()], 500);
        }
    }
}
