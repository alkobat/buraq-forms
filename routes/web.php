use Illuminate\Support\Facades\Route;

Route::get('/evaluations', [EvaluationController::class, 'index']);