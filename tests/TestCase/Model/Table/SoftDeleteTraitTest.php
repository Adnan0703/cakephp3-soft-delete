<?php
namespace SoftDelete\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;
use Cake\Network\Exception\InternalErrorException;

/**
 * App\Model\Behavior\SoftDeleteBehavior Test Case
 */
class SoftDeleteBehaviorTest extends TestCase
{

    /**
     * fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.SoftDelete.users',
        'plugin.SoftDelete.blog_posts'
        ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->usersTable = TableRegistry::get('Users', ['className' => 'SoftDelete\Test\Fixture\UsersTable']);
        $this->postsTable = TableRegistry::get('BlogPosts', ['className' => 'SoftDelete\Test\Fixture\BlogPostsTable']);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->usersTable);
        unset($this->postsTable);
        parent::tearDown();
    }

    /**
     * Tests that a soft deleted entities is not found when calling Table::find()
     */
    public function testFind()
    {
        $user = $this->usersTable->get(1);
        $user->deleted = date('Y-m-d H:i:s');
        $this->usersTable->save($user);

        $user = $this->usersTable->find()->where(['id' => 1])->first();
        $this->assertEquals(null, $user);

    }

    /**
     * Tests that a soft deleted entities is not found when calling Table::findByXXX()
     */
    public function testDynamicFinder()
    {
        $user = $this->usersTable->get(1);
        $user->deleted = date('Y-m-d H:i:s');
        $this->usersTable->save($user);

        $user = $this->usersTable->findById(1)->first();
        $this->assertEquals(null, $user);
    }

    /**
     * Tests that Table::deleteAll() does not hard delete
     */
    public function testDeleteAll()
    {
        $this->usersTable->deleteAll([]);
        $this->assertEquals(0, $this->usersTable->find()->count());
        $this->assertNotEquals(0, $this->usersTable->find('all', ['withDeleted'])->count());

        $this->postsTable->deleteAll([]);
        $this->assertEquals(0, $this->postsTable->find()->count());
        $this->assertNotEquals(0, $this->postsTable->find('all', ['withDeleted'])->count());

    }

    /**
     * Tests that Table::delete() does not hard delete.
     */
    public function testDelete()
    {
        $user = $this->usersTable->get(1);
        $this->usersTable->delete($user);
        $user = $this->usersTable->findById(1)->first();
        $this->assertEquals(null, $user);

        $user = $this->usersTable->find('all', ['withDeleted'])->where(['id' => 1])->first();
        $this->assertNotEquals(null, $user);
        $this->assertNotEquals(null, $user->deleted);
    }

    /**
     * Tests that soft deleting an entity also soft deletes its belonging entities.
     */
    public function testHasManyAssociation()
    {
        $user = $this->usersTable->get(1);
        $this->usersTable->delete($user);

        $count = $this->postsTable->find()->where(['user_id' => 1])->count();
        $this->assertEquals(0, $count);

        $count = $this->postsTable->find('all', ['withDeleted'])->where(['user_id' => 1])->count();
        $this->assertEquals(2, $count);
    }

    /**
     * Tests that soft deleting affects counters the same way that hard deleting.
     */
    public function testCounterCache()
    {
        $post = $this->postsTable->get(1);
        $this->postsTable->delete($post);
        $this->assertNotEquals(null, $this->postsTable->find('all', ['withDeleted'])->where(['id' => 1])->first());
        $this->assertEquals(null, $this->postsTable->findById(1)->first());

        $user = $this->usersTable->get(1);
        $this->assertEquals(1, $user->posts_count);
    }

    public function testHardDelete()
    {
        $user = $this->usersTable->get(1);
        $this->usersTable->hardDelete($user);
        $user = $this->usersTable->findById(1)->first();
        $this->assertEquals(null, $user);

        $user = $this->usersTable->find('all', ['withDeleted'])->where(['id' => 1])->first();
        $this->assertEquals(null, $user);
    }

    /**
     * Tests hardDeleteAll.
     */
    public function testHardDeleteAll()
    {
        $affectedRows = $this->postsTable->hardDeleteAll(new \DateTime('now'));
        $this->assertEquals(0, $affectedRows);

        $postsRowsCount = $this->postsTable->find('all', ['withDeleted'])->count();

        $this->postsTable->delete($this->postsTable->get(1));
        $affectedRows = $this->postsTable->hardDeleteAll(new \DateTime('now'));
        $this->assertEquals(1, $affectedRows);

        $newpostsRowsCount = $this->postsTable->find('all', ['withDeleted'])->count();
        $this->assertEquals($postsRowsCount - 1, $newpostsRowsCount);
    }
}
