<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class UsuarioController extends Controller
{
    public const ROLES = [
        'admin' => 'Administrador',
        'gerente' => 'Gerente',
        'vendedor' => 'Vendedor',
    ];

    public function index(Request $request)
    {
        $q = $request->get('q');

        $usuarios = User::when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('usuarios.index', ['usuarios' => $usuarios, 'q' => $q, 'roles' => self::ROLES]);
    }

    public function create()
    {
        return view('usuarios.create', ['usuario' => new User(['activo' => true, 'rol' => 'vendedor']), 'roles' => self::ROLES]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'rol' => ['required', 'in:admin,gerente,vendedor'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], $this->mensajes());

        $data['activo'] = $request->boolean('activo');
        User::create($data);

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        return view('usuarios.edit', ['usuario' => $usuario, 'roles' => self::ROLES]);
    }

    public function update(Request $request, User $usuario)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $usuario->id],
            'rol' => ['required', 'in:admin,gerente,vendedor'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'confirmed', Password::min(6)],
        ], $this->mensajes());

        // Evita que un admin se quite a sí mismo el rol de admin
        if ($usuario->id === Auth::id() && $data['rol'] !== 'admin') {
            return back()->with('error', 'No puedes cambiar tu propio rol de administrador.');
        }

        $data['activo'] = $request->boolean('activo');
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $usuario->update($data);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === Auth::id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($usuario->ventas()->exists()) {
            return back()->with('error', 'No se puede eliminar: el usuario tiene ventas registradas. Desactívalo en su lugar.');
        }

        $usuario->delete();

        return back()->with('success', 'Usuario eliminado.');
    }

    private function mensajes(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.unique' => 'Este correo ya está registrado.',
            'rol.required' => 'Selecciona un rol.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ];
    }
}
