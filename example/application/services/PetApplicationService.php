<?PHP
namespace PSwag\Example\Application\Services;

use PSwag\Example\Application\Dtos\Category;
use PSwag\Example\Application\Dtos\Pet;

class PetApplicationService
{
    /**
     * Find pet by ID
     * @param int $petId ID of pet to return
     * @return Pet Returns a single pet
     */
    public function getPetById(int $petId): Pet {
        return new Pet(
            1,
            new Category(1, 'Category 1'),
            'Moritz',
            [],
            [],
            'Status 1'
        );
    }

    /**
     * Update an exiting pet
     * @param Pet $pet Pet object that needs to be added to the store
     */
    public function updatePetById(Pet $pet): void {

    }

    /**
     * Deletes a pet
     * @param int $petId Pet id to delete
     */
    public function deletePetById(int $petId): void {

    }

    /**
     * Add a new pet to the store
     * @param Pet $pet Pet object that needs to be added to the store
     */
    public function createNewPet(Pet $pet): void {

    }
}
?>