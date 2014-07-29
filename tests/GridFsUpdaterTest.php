<?php
namespace Gaillard\Mongo;

final class GridFsUpdaterTest extends \PHPUnit_Framework_TestCase
{
    private $gridfs;

    public function setUp()
    {
        parent::setUp();

        $db = (new \MongoClient())->selectDB('gridFsUpdaterTests');
        $db->drop();

        $this->gridfs = $db->getGridFS();
    }

    /**
     * @test
     */
    public function updateModifierDoc()
    {
        $id = new \MongoId();

        $this->gridfs->storeBytes('1234', ['_id' => $id, 'key1' => 1, 'key2' => 2, 'key3' => true]);

        GridFsUpdater::update(
            $this->gridfs,
            $id,
            '1234',
            [
                '$set' => ['key2' => -2, 'chunkSize' => 'BAD', 'length' => 'BAD', 'md5' => 'BAD'],
                '$unset' => ['key3' => ''],
            ],
            ['chunkSize' => 2]
        );

        $result = $this->gridfs->findOne()->file;

        $expected = [
            '_id' => $id,
            'chunkSize' => 2,
            'key1' => 1,
            'key2' => -2,
            'length' => 4,
            'md5' => '81dc9bdb52d04dc20036dbd8313ed055',
            'uploadDate' => $result['uploadDate'],
        ];

        ksort($result);
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function updateLongerAndSameAsChunkSize()
    {
        $id = new \MongoId();

        $this->gridfs->storeBytes('1234', ['_id' => $id, 'chunkSize' => 2]);

        $this->assertSame(2, $this->gridfs->chunks->count());

        GridFsUpdater::update($this->gridfs, $id, '123456', [], ['chunkSize' => 6]);

        $filesResult = $this->gridfs->findOne()->file;

        $this->assertSame(6, $filesResult['chunkSize']);
        $this->assertSame(6, $filesResult['length']);
        $this->assertSame('e10adc3949ba59abbe56e057f20f883e', $filesResult['md5']);

        $this->assertSame(1, $this->gridfs->chunks->count());

        $this->assertSame('123456', $this->gridfs->findOne()->getBytes());
    }

    /**
     * @test
     */
    public function updateBytesSameLength()
    {
        $id = new \MongoId();

        $this->gridfs->storeBytes('1234', ['_id' => $id, 'chunkSize' => 2]);

        $this->assertSame(2, $this->gridfs->chunks->count());

        GridFsUpdater::update($this->gridfs, $id, '5678', [], ['chunkSize' => 2]);

        $filesResult = $this->gridfs->findOne()->file;

        $this->assertSame(2, $filesResult['chunkSize']);
        $this->assertSame(4, $filesResult['length']);
        $this->assertSame('674f3c2c1a8a6f90461e8a66fb5550ba', $filesResult['md5']);

        $this->assertSame(2, $this->gridfs->chunks->count());

        $this->assertSame('5678', $this->gridfs->findOne()->getBytes());
    }

    /**
     * @test
     */
    public function updateBytesLongerByOneChunk()
    {
        $id = new \MongoId();

        $this->gridfs->storeBytes('1234', ['_id' => $id, 'chunkSize' => 2]);

        $this->assertSame(2, $this->gridfs->chunks->count());

        GridFsUpdater::update($this->gridfs, $id, '123456', [], ['chunkSize' => 2]);

        $filesResult = $this->gridfs->findOne()->file;

        $this->assertSame(2, $filesResult['chunkSize']);
        $this->assertSame(6, $filesResult['length']);
        $this->assertSame('e10adc3949ba59abbe56e057f20f883e', $filesResult['md5']);

        $this->assertSame(3, $this->gridfs->chunks->count());

        $this->assertSame('123456', $this->gridfs->findOne()->getBytes());
    }

    /**
     * @test
     */
    public function updateBytesShorterByOneChunk()
    {
        $id = new \MongoId();

        $this->gridfs->storeBytes('123456', ['_id' => $id, 'chunkSize' => 2]);

        $this->assertSame(3, $this->gridfs->chunks->count());

        GridFsUpdater::update($this->gridfs, $id, '1234', [], ['chunkSize' => 2]);

        $filesResult = $this->gridfs->findOne()->file;

        $this->assertSame(2, $filesResult['chunkSize']);
        $this->assertSame(4, $filesResult['length']);
        $this->assertSame('81dc9bdb52d04dc20036dbd8313ed055', $filesResult['md5']);

        $this->assertSame(2, $this->gridfs->chunks->count());

        $this->assertSame('1234', $this->gridfs->findOne()->getBytes());
    }

    /**
     * @test
     */
    public function updateSomethingToZeroBytes()
    {
        $id = new \MongoId();

        $this->gridfs->storeBytes('1234', ['_id' => $id, 'chunkSize' => 2]);

        $this->assertSame(2, $this->gridfs->chunks->count());

        GridFsUpdater::update($this->gridfs, $id, '', [], ['chunkSize' => 2]);

        $filesResult = $this->gridfs->findOne()->file;

        $this->assertSame(2, $filesResult['chunkSize']);
        $this->assertSame(0, $filesResult['length']);
        $this->assertSame('d41d8cd98f00b204e9800998ecf8427e', $filesResult['md5']);

        $this->assertSame(0, $this->gridfs->chunks->count());
    }

    /**
     * @test
     */
    public function updateZeroToSomethingBytes()
    {
        $id = new \MongoId();

        $this->gridfs->storeBytes('', ['_id' => $id, 'chunkSize' => 2]);

        $this->assertSame(0, $this->gridfs->chunks->count());

        GridFsUpdater::update($this->gridfs, $id, '1234', [], ['chunkSize' => 2]);

        $filesResult = $this->gridfs->findOne()->file;

        $this->assertSame(2, $filesResult['chunkSize']);
        $this->assertSame(4, $filesResult['length']);
        $this->assertSame('81dc9bdb52d04dc20036dbd8313ed055', $filesResult['md5']);

        $this->assertSame(2, $this->gridfs->chunks->count());

        $this->assertSame('1234', $this->gridfs->findOne()->getBytes());
    }

    /**
     * @test
     * @expectedException \Exception
     * @expectedExceptionMessage _id '53d7d7fd387bcffe598b457a' did not exist to update in gridfs files
     */
    public function updateNotFoundId()
    {
        GridFsUpdater::update($this->gridfs, new \MongoId('53d7d7fd387bcffe598b457a'), '1234');

        $this->assertSame(0, $this->gridfs->count());
        $this->assertSame(0, $this->gridfs->chunks->count());
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage chunkSize option need to be an integer >= 1
     */
    public function updateNegativeChunkSize()
    {
        GridFsUpdater::update($this->gridfs, new \MongoId(), '1234', [], ['chunkSize' => -1]);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $bytes must be a string
     */
    public function updateNonStringBytes()
    {
        GridFsUpdater::update($this->gridfs, new \MongoId(), true);
    }
}
