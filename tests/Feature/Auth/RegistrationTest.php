<?php

namespace Tests\Feature\Auth;

use App\Models\Course;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_students_can_register_with_a_class_code(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = $teacher->courses()->create(['name' => 'Algoritma dan Pemrograman']);
        $class = $course->classes()->create(['name' => 'Kelas A', 'code' => 'ABC234']);

        $response = $this->post('/register', [
            'name' => 'Mahasiswa Uji',
            'email' => 'mahasiswa@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'class_code' => 'abc234', // huruf kecil sengaja — harus dinormalkan
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('course.show', $class));

        $student = User::where('email', 'mahasiswa@example.com')->firstOrFail();
        $this->assertSame('student', $student->role);
        $this->assertDatabaseHas('enrollments', [
            'class_id' => $class->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_registration_fails_with_an_unknown_class_code(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Mahasiswa Uji',
            'email' => 'mahasiswa@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'class_code' => 'ZZZZZZ',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('class_code');
        $this->assertGuest();
    }
}
