<?php
namespace App\Models;

use PDO;
use App\Utils\Database;

class Vehicle {
    private $pdo;

    public $id;
    public $customer_id;
    public $make;
    public $model;
    public $year;
    public $vin;
    public $license_plate;
    public $color;
    public $notes;
    public $created_at;
    public $updated_at;

    // For display purposes when listing vehicles
    public $customer_full_name;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAllByCustomer(int $customerId): array {
        $stmt = $this->pdo->prepare(
            "SELECT v.*, c.full_name as customer_full_name
             FROM vehicles v
             JOIN customers c ON v.customer_id = c.id
             WHERE v.customer_id = :customer_id
             ORDER BY v.make ASC, v.model ASC"
        );
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(int $limit = 100, int $offset = 0): array {
        $stmt = $this->pdo->prepare(
            "SELECT v.*, c.full_name as customer_full_name
             FROM vehicles v
             JOIN customers c ON v.customer_id = c.id
             ORDER BY c.full_name ASC, v.make ASC, v.model ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function findById(int $id): ?self {
        $stmt = $this->pdo->prepare("SELECT * FROM vehicles WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $vehicle = new self();
            foreach ($data as $key => $value) {
                if (property_exists($vehicle, $key)) {
                    $vehicle->$key = $value;
                }
            }
            return $vehicle;
        }
        return null;
    }

    public function findByVin(string $vin): ?self {
        $stmt = $this->pdo->prepare("SELECT * FROM vehicles WHERE vin = :vin");
        $stmt->bindParam(':vin', $vin);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
         if ($data) {
            $vehicle = new self();
            foreach ($data as $key => $value) {
                if (property_exists($vehicle, $key)) {
                    $vehicle->$key = $value;
                }
            }
            return $vehicle;
        }
        return null;
    }

    public function findByLicensePlate(string $licensePlate): ?self {
        $stmt = $this->pdo->prepare("SELECT * FROM vehicles WHERE license_plate = :license_plate");
        $stmt->bindParam(':license_plate', $licensePlate);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
         if ($data) {
            $vehicle = new self();
            foreach ($data as $key => $value) {
                if (property_exists($vehicle, $key)) {
                    $vehicle->$key = $value;
                }
            }
            return $vehicle;
        }
        return null;
    }

    public function create(array $data): ?int {
        if (empty($data['customer_id']) || (empty($data['vin']) && empty($data['license_plate']))) {
            error_log("Vehicle creation failed: Customer ID and (VIN or License Plate) are required.");
            return null;
        }
        // Basic validation
        if (!empty($data['year']) && (!is_numeric($data['year']) || $data['year'] < 1900 || $data['year'] > (date('Y') + 1))) {
             error_log("Vehicle creation failed: Invalid year provided.");
            return null;
        }


        $sql = "INSERT INTO vehicles (customer_id, make, model, year, vin, license_plate, color, notes)
                VALUES (:customer_id, :make, :model, :year, :vin, :license_plate, :color, :notes)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindParam(':customer_id', $data['customer_id'], PDO::PARAM_INT);
        $stmt->bindValue(':make', $data['make'] ?? null);
        $stmt->bindValue(':model', $data['model'] ?? null);
        $stmt->bindValue(':year', $data['year'] ? (int)$data['year'] : null, PDO::PARAM_INT_OR_NULL);
        $stmt->bindValue(':vin', $data['vin'] ?? null);
        $stmt->bindValue(':license_plate', $data['license_plate'] ?? null);
        $stmt->bindValue(':color', $data['color'] ?? null);
        $stmt->bindValue(':notes', $data['notes'] ?? null);

        try {
            $stmt->execute();
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Vehicle creation DB error: " . $e->getMessage() . " (Code: {$e->getCode()})");
            // Code 23000 for integrity constraint (e.g. duplicate VIN/license if unique)
            return null;
        }
    }

    public function update(int $id, array $data): bool {
         if (empty($data['customer_id']) || (empty($data['vin']) && empty($data['license_plate']))) {
            error_log("Vehicle update failed for ID {$id}: Customer ID and (VIN or License Plate) are required.");
            return false;
        }
        if (!empty($data['year']) && (!is_numeric($data['year']) || $data['year'] < 1900 || $data['year'] > (date('Y') + 1))) {
             error_log("Vehicle update failed for ID {$id}: Invalid year provided.");
            return false;
        }

        $sql = "UPDATE vehicles SET
                    customer_id = :customer_id,
                    make = :make,
                    model = :model,
                    year = :year,
                    vin = :vin,
                    license_plate = :license_plate,
                    color = :color,
                    notes = :notes
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':customer_id', $data['customer_id'], PDO::PARAM_INT);
        $stmt->bindValue(':make', $data['make'] ?? null);
        $stmt->bindValue(':model', $data['model'] ?? null);
        $stmt->bindValue(':year', $data['year'] ? (int)$data['year'] : null, PDO::PARAM_INT_OR_NULL);
        $stmt->bindValue(':vin', $data['vin'] ?? null);
        $stmt->bindValue(':license_plate', $data['license_plate'] ?? null);
        $stmt->bindValue(':color', $data['color'] ?? null);
        $stmt->bindValue(':notes', $data['notes'] ?? null);

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Vehicle update DB error for ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool {
        // Consider if there are job cards associated with this vehicle.
        // The schema for job_cards has `jc_vehicle_id_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON UPDATE CASCADE
        // This means if a vehicle ID changes, it updates. Deletion is not specified, so it might be RESTRICT by default.
        // If it's RESTRICT, deletion will fail if job cards exist.
        // For now, attempt hard delete.
        try {
            $stmt = $this->pdo->prepare("DELETE FROM vehicles WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Vehicle deletion DB error for ID {$id}: " . $e->getMessage());
            // If deletion is restricted due to job cards, this will fail.
            return false;
        }
    }

    public function searchByPlateOrVIN(string $searchTerm): array {
        $searchTermLike = "%" . $searchTerm . "%";
        $stmt = $this->pdo->prepare(
            "SELECT v.id, v.license_plate, v.vin, v.make, v.model, c.full_name as customer_name, c.id as customer_id
             FROM vehicles v
             JOIN customers c ON v.customer_id = c.id
             WHERE v.license_plate LIKE :term
                OR v.vin LIKE :term
             ORDER BY v.license_plate ASC
             LIMIT 20"
        );
        $stmt->bindParam(':term', $searchTermLike);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
