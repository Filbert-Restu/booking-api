<?php

namespace Database\Seeders;

use App\Models\Sign;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SignatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some users to add signatures
        $users = User::whereIn('email', [
            'sekretaris.hmif@student.undip.ac.id',
            'ketua.hmif@student.undip.ac.id',
            'ketua.madani@student.undip.ac.id',
        ])->get();

        if ($users->isEmpty()) {
            echo "⚠️ No users found. Skipping signature seeding.\n";
            return;
        }

        // Create signatures directory
        $signatureDir = storage_path('app/signatures');
        if (!file_exists($signatureDir)) {
            mkdir($signatureDir, 0755, true);
            echo "📁 Created directory: $signatureDir\n";
        }

        foreach ($users as $user) {
            // Check if user already has signature
            $existing = Sign::where('user_id', $user->id)->first();
            if ($existing) {
                echo "⏭️ Signature already exists for: {$user->name}\n";
                continue;
            }

            // Create simple PNG signature
            $fileName = uniqid() . '_signature_' . $user->id . '.png';
            $filePath = 'signatures/' . $fileName;
            $fullPath = storage_path('app/' . $filePath);

            // Create a simple PNG image (1x1 transparent pixel for testing)
            // This is just for seeding - real signatures should be uploaded by users
            $this->createSimpleSignatureImage($fullPath, $user->name);

            // Insert signature record
            Sign::create([
                'user_id' => $user->id,
                'signature' => $filePath,
            ]);

            echo "✅ Created signature for: {$user->name}\n";
        }

        echo "\n🎉 Signature seeding completed!\n";
        echo "📍 Location: storage/app/signatures/\n";
        echo "⚠️ Note: These are test signatures. Users should upload real signatures.\n";
    }

    /**
     * Create a simple signature image for testing
     */
    protected function createSimpleSignatureImage(string $path, string $name): void
    {
        // Create minimal valid PNG file (1x1 transparent pixel)
        // PNG header + IHDR + IDAT + IEND chunks
        $pngData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAFUlEQVR42mNk+M9Qz0AEYBxVSF+FAAhKDveksOjuAAAAAElFTkSuQmCC'
        );

        file_put_contents($path, $pngData);
    }
}
