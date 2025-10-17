use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleAndAdminSeeder extends Seeder
{
    public function run()
    {
        Role::create(['name' => 'admin']);
    }
}