<?php namespace Vinelab\NeoEloquent\Tests\Functional\QueryingRelations;

use Mockery as M;
use Vinelab\NeoEloquent\Tests\TestCase;
use Vinelab\NeoEloquent\Eloquent\Model;

class QueryingRelationsTest extends TestCase {

    public function testQueryingHasCount()
    {
        $postNoComment   = Post::create(['title' => 'I have no comments =(', 'body' => 'None!']);
        $postWithComment = Post::create(['title' => 'Nananana', 'body' => 'Commentmaaan']);
        $postWithTwoComments = Post::create(['title' => 'I got two']);
        $postWithTenComments = Post::create(['tite' => 'Up yours posts, got 10 here']);

        $comment = new Comment(['text' => 'food']);
        $postWithComment->comments()->save($comment);

        // add two comments to $postWithTwoComments
        for($i = 0; $i < 2; $i++)
        {
            $postWithTwoComments->comments()->create(['text' => "Comment $i"]);
        }
        // add ten comments to $postWithTenComments
        for ($i = 0; $i < 10; $i++)
        {
            $postWithTenComments->comments()->create(['text' => "Comment $i"]);
        }

        $allPosts = Post::get();
        $this->assertEquals(4, count($allPosts));

        $posts = Post::has('comments')->get();
        $this->assertEquals(3, count($posts));
        $expectedHasComments = [$postWithComment->id, $postWithTwoComments->id, $postWithTenComments->id];
        foreach ($posts as $key => $post)
        {
            $this->assertTrue(in_array($post->id, $expectedHasComments));
        }

        $postsWithMoreThanOneComment = Post::has('comments', '>=', 2)->get();
        $this->assertEquals(2, count($postsWithMoreThanOneComment));
        $expectedWithMoreThanOne = [$postWithTwoComments->id, $postWithTenComments->id];
        foreach ($postsWithMoreThanOneComment as $post)
        {
            $this->assertTrue(in_array($post->id, $expectedWithMoreThanOne));
        }

        $postWithTen = Post::has('comments', '=', 10)->get();
        $this->assertEquals(1, count($postWithTen));
        $this->assertEquals($postWithTenComments->toArray(), $postWithTen->first()->toArray());
    }

    public function testQueryingWhereHasOne()
    {
        $mrAdmin        = User::create(['name' => 'Rundala']);
        $anotherAdmin   = User::create(['name' => 'Makhoul']);
        $mrsEditor      = User::create(['name' => 'Mr. Moonlight']);
        $mrsManager     = User::create(['name' => 'Batista']);
        $anotherManager = User::create(['name' => 'Quin Tukee']);

        $admin   = Role::create(['alias' => 'admin']);
        $editor  = Role::create(['alias' => 'editor']);
        $manager = Role::create(['alias' => 'manager']);

        $mrAdmin->roles()->save($admin);
        $anotherAdmin->roles()->save($admin);
        $mrsEditor->roles()->save($editor);
        $mrsManager->roles()->save($manager);
        $anotherManager->roles()->save($manager);

        // check admins
        $admins = User::whereHas('roles', function($q) { $q->where('alias', 'admin'); })->get();
        $this->assertEquals(2, count($admins));
        $expectedAdmins = [$mrAdmin, $anotherAdmin];
        foreach ($admins as $key => $admin)
        {
            $this->assertEquals($admin->toArray(), $expectedAdmins[$key]->toArray());
        }
        // check editors
        $editors = User::whereHas('roles', function($q) { $q->where('alias', 'editor'); })->get();
        $this->assertEquals(1, count($editors));
        $this->assertEquals($mrsEditor->toArray(), $editors->first()->toArray());
        // check managers
        $expectedManagers = [$mrsManager, $anotherManager];
        $managers = User::whereHas('roles', function($q) { $q->where('alias', 'manager'); })->get();
        $this->assertEquals(2, count($managers));
        foreach ($managers as $key => $manager)
        {
            $this->assertEquals($manager->toArray(), $expectedManagers[$key]->toArray());
        }
    }

    public function testQueryingWhereHasById()
    {
        $user = User::create(['name' => 'cappuccino']);
        $role = Role::create(['alias' => 'pikachu']);

        $user->roles()->save($role);

        $found = User::whereHas('roles', function($q) use ($role)
        {
            $q->where('id', $role->getKey());
        })->first();

        $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\User', $found);
        $this->assertEquals($user->toArray(), $found->toArray());
    }

    public function testQueryingParentWithWhereHas()
    {
        $user = User::create(['name' => 'cappuccino']);
        $role = Role::create(['alias' => 'pikachu']);

        $user->roles()->save($role);

        $found = User::whereHas('roles', function($q) use ($role)
        {
            $q->where('id', $role->id);
        })->where('id', $user->id)->first();

        $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\User', $found);
        $this->assertEquals($user->toArray(), $found->toArray());
    }

    public function testCreatingModelWithSingleRelation()
    {
        $account = ['guid' => uniqid()];
        $user = User::createWith(['name' => 'Misteek'], compact('account'));

        $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\User', $user);
        $this->assertTrue($user->exists);
        $this->assertGreaterThanOrEqual(0, $user->id);

        $related = $user->account;
        $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Account', $related);
        $attrs = $related->toArray();
        unset($attrs['id']);
        $this->assertEquals($account, $attrs);
    }

    public function testCreatingModelWithRelations()
    {
        // Creating a role with its permissions.
        $role = ['title' => 'Admin', 'alias' => 'admin'];

        $permissions = [
            new Permission(['title' => 'Create Records', 'alias' => 'create', 'dodid' => 'done']),
            new Permission(['title' => 'Read Records', 'alias'   => 'read', 'dont be so' => 'down']),
            ['title' => 'Update Records', 'alias' => 'update'],
            ['title' => 'Delete Records', 'alias' => 'delete']
        ];

        $role = Role::createWith($role, compact('permissions'));

        $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Role', $role);
        $this->assertTrue($role->exists);
        $this->assertGreaterThanOrEqual(0, $role->id);

        foreach ($role->permissions as $key => $permission)
        {
            $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Permission', $permission);
            $this->assertGreaterThan(0, $permission->id);
            $attrs = $permission->toArray();
            unset($attrs['id']);
            if ($permissions[$key] instanceof Permission)
            {
                $this->assertEquals($permissions[$key]->toArray(), $attrs);
            } else
            {
                $this->assertEquals($permissions[$key], $attrs);
            }
        }
    }

    public function testCreatingModelWithMultipleRelationTypes()
    {
        $post = ['title' => 'Trip to Bedlam', 'body' => 'It was wonderful! Check the embedded media'];

        $photos = [
            [
                'url'      => 'http://somewere.in.bedlam.net',
                'caption'  => 'Gunatanamo',
                'metadata' => '...'
            ],
            [
                'url'      => 'http://another-place.in.bedlam.net',
                'caption'  => 'Gunatanamo',
                'metadata' => '...'
            ],
        ];

        $videos = [
            [
                'title'       => 'Fun at the borders',
                'description' => 'Once upon a time...',
                'stream_url'  => 'http://stream.that.shit.io',
                'thumbnail'   => 'http://sneak.peek.io'
            ]
        ];

        $post = Post::createWith($post, compact('photos', 'videos'));

        $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Post', $post);
        $this->assertTrue($post->exists);
        $this->assertGreaterThanOrEqual(0, $post->id);

        foreach ($post->photos as $key => $photo)
        {
            $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Photo', $photo);
            $this->assertGreaterThan(0, $photo->id);
            $attrs = $photo->toArray();
            unset($attrs['id']);
            $this->assertEquals($photos[$key], $attrs);
        }

        $video = $post->videos->first();
        $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Video', $video);
        $attrs = $video->toArray();
        unset($attrs['id']);
        $this->assertEquals($videos[0], $attrs);
    }

    public function testCreatingModelWithSingleInverseRelation()
    {
        $user = ['name' => 'Some Name'];
        $account = Account::createWith(['guid' => 'globalid'], compact('user'));

        $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Account', $account);
        $this->assertTrue($account->exists);
        $this->assertGreaterThanOrEqual(0, $account->id);

        $related = $account->user;
        $attrs = $related->toArray();
        unset($attrs['id']);
        $this->assertEquals($attrs, $user);
    }

    public function testCreatingModelWithMultiInverseRelations()
    {
        $users = new User(['name' => 'safastak']);
        $role = Role::createWith(['alias'=>'admin'], compact('users'));

        $this->assertInstanceOf('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Role', $role);
        $this->assertTrue($role->exists);
        $this->assertGreaterThanOrEqual(0, $role->id);

        $related = $role->users->first();
        $attrs = $related->toArray();
        unset($attrs['id']);
        $this->assertEquals($attrs, $users->toArray());
    }

}

class User extends Model {

    protected $label = 'User';

    protected $fillable = ['name'];

    public function roles()
    {
        return $this->hasMany('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Role', 'PERMITTED');
    }

    public function account()
    {
        return $this->hasOne('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Account', 'ACCOUNT');
    }
}

class Account extends Model {

    protected $label = 'Account';

    protected $fillable = ['guid'];

    public function user()
    {
        return $this->belongsTo('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\User', 'ACCOUNT');
    }
}

class Role extends Model {

    protected $label = 'Role';

    protected $fillable = ['title', 'alias'];

    public function users()
    {
        return $this->belongsToMany('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\User', 'PERMITTED');
    }

    public function permissions()
    {
        return $this->hasMany('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Permission', 'ALLOWS');
    }
}

class Permission extends Model {

    protected $label = 'Permission';

    protected $fillable = ['title', 'alias'];

    public function roles()
    {
        return $this->belongsToMany('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Role', 'ALLOWS');
    }
}

class Post extends Model {

    protected $label = 'Post';

    protected $fillable = ['title', 'body'];

    public function photos()
    {
        return $this->hasMany('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Photo', 'PHOTO');
    }

    public function videos()
    {
        return $this->hasMany('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Video', 'VIDEO');
    }

    public function comments()
    {
        return $this->hasMany('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Comment', 'COMMENT');
    }
}

class Photo extends Model {

    protected $label = 'Photo';

    protected $fillable = ['url', 'caption', 'metadata'];
}

class Video extends Model {

    protected $label = 'Video';

    protected $fillable = ['title', 'description', 'stream_url', 'thumbnail'];
}

class Comment extends Model {

    protected $label = 'Comment';

    protected $fillable = ['text'];

    public function post()
    {
        return $this->belongsTo('Vinelab\NeoEloquent\Tests\Functional\QueryingRelations\Post', 'COMMENT');
    }
}
