<?php namespace Tests;

use BookStack\Image;
use BookStack\Page;

class ImageTest extends TestCase
{
    /**
     * Get the path to our basic test image.
     * @return string
     */
    protected function getTestImageFilePath()
    {
        return base_path('tests/test-data/test-image.png');
    }

    /**
     * Get a test image that can be uploaded
     * @param $fileName
     * @return \Illuminate\Http\UploadedFile
     */
    protected function getTestImage($fileName)
    {
        return new \Illuminate\Http\UploadedFile($this->getTestImageFilePath(), $fileName, 'image/jpeg', 5238);
    }

    /**
     * Get the path for a test image.
     * @param $type
     * @param $fileName
     * @return string
     */
    protected function getTestImagePath($type, $fileName)
    {
        return '/uploads/images/' . $type . '/' . Date('Y-m-M') . '/' . $fileName;
    }

    /**
     * Uploads an image with the given name.
     * @param $name
     * @param int $uploadedTo
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    protected function uploadImage($name, $uploadedTo = 0)
    {
        $file = $this->getTestImage($name);
        return $this->call('POST', '/images/gallery/upload', ['uploaded_to' => $uploadedTo], [], ['file' => $file], []);
    }

    /**
     * Delete an uploaded image.
     * @param $relPath
     */
    protected function deleteImage($relPath)
    {
        $path = public_path($relPath);
        if (file_exists($path)) {
            unlink($path);
        }
    }


    public function test_image_upload()
    {
        $page = Page::first();
        $admin = $this->getAdmin();
        $this->actingAs($admin);

        $imageName = 'first-image.png';
        $relPath = $this->getTestImagePath('gallery', $imageName);
        $this->deleteImage($relPath);

        $upload = $this->uploadImage($imageName, $page->id);
        $upload->assertStatus(200);

        $this->assertTrue(file_exists(public_path($relPath)), 'Uploaded image not found at path: '. public_path($relPath));

        $this->deleteImage($relPath);

        $this->assertDatabaseHas('images', [
            'url' => $this->baseUrl . $relPath,
            'type' => 'gallery',
            'uploaded_to' => $page->id,
            'path' => $relPath,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'name' => $imageName
        ]);

    }

    public function test_image_delete()
    {
        $page = Page::first();
        $this->asAdmin();
        $imageName = 'first-image.png';

        $this->uploadImage($imageName, $page->id);
        $image = Image::first();
        $relPath = $this->getTestImagePath('gallery', $imageName);

        $delete = $this->delete( '/images/' . $image->id);
        $delete->assertStatus(200);

        $this->assertDatabaseMissing('images', [
            'url' => $this->baseUrl . $relPath,
            'type' => 'gallery'
        ]);

        $this->assertFalse(file_exists(public_path($relPath)), 'Uploaded image has not been deleted as expected');
    }

    public function testBase64Get()
    {
        $page = Page::first();
        $this->asAdmin();
        $imageName = 'first-image.png';

        $this->uploadImage($imageName, $page->id);
        $image = Image::first();

        $imageGet = $this->getJson("/images/base64/{$image->id}");
        $imageGet->assertJson([
            'content' => 'iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAIAAAACDbGyAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4gEcDCo5iYNs+gAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAAFElEQVQI12O0jN/KgASYGFABqXwAZtoBV6Sl3hIAAAAASUVORK5CYII='
        ]);
    }

    public function test_drawing_base64_upload()
    {
        $page = Page::first();
        $editor = $this->getEditor();
        $this->actingAs($editor);

        $upload = $this->postJson('images/drawing/upload', [
            'uploaded_to' => $page->id,
            'image' => 'image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAIAAAACDbGyAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4gEcDCo5iYNs+gAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAAFElEQVQI12O0jN/KgASYGFABqXwAZtoBV6Sl3hIAAAAASUVORK5CYII='
        ]);

        $upload->assertStatus(200);
        $upload->assertJson([
            'type' => 'drawio',
            'uploaded_to' => $page->id,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $image = Image::where('type', '=', 'drawio')->first();
        $this->assertTrue(file_exists(public_path($image->path)), 'Uploaded image not found at path: '. public_path($image->path));

        $testImageData = file_get_contents($this->getTestImageFilePath());
        $uploadedImageData = file_get_contents(public_path($image->path));
        $this->assertTrue($testImageData === $uploadedImageData, "Uploaded image file data does not match our test image as expected");
    }

    public function test_drawing_replacing()
    {
        $page = Page::first();
        $editor = $this->getEditor();
        $this->actingAs($editor);

        $this->postJson('images/drawing/upload', [
            'uploaded_to' => $page->id,
            'image' => 'image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAIAAAACDbGyAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4gEcDQ4S1RUeKwAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAAFElEQVQI12NctNWSAQkwMaACUvkAfCkBmjyhGl4AAAAASUVORK5CYII='
        ]);

        $image = Image::where('type', '=', 'drawio')->first();

        $replace = $this->putJson("images/drawing/upload/{$image->id}", [
            'image' => 'image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAIAAAACDbGyAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4gEcDCo5iYNs+gAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAAFElEQVQI12O0jN/KgASYGFABqXwAZtoBV6Sl3hIAAAAASUVORK5CYII='
        ]);

        $replace->assertStatus(200);
        $replace->assertJson([
            'type' => 'drawio',
            'uploaded_to' => $page->id,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $this->assertTrue(file_exists(public_path($image->path)), 'Uploaded image not found at path: '. public_path($image->path));

        $testImageData = file_get_contents($this->getTestImageFilePath());
        $uploadedImageData = file_get_contents(public_path($image->path));
        $this->assertTrue($testImageData === $uploadedImageData, "Uploaded image file data does not match our test image as expected");
    }

}