<?php
namespace Gaillard\Mongo;

final class GridFsUpdater
{
    /**
     * Update gridfs file in-place.
     *
     * @param \MongoGridFS $grid grid instance
     * @param mixed $id the id. Any compatible type for a mongo _id
     * @param string $bytes byte string
     * @param array $modifierDoc update document with modifiers ($set, $unset etc)
     * @param array $options options. Currently only chunkSize (int)
     *
     * @throws \Exception
     */
    public static function update(\MongoGridFS $grid, $id, $bytes, array $modifierDoc = [], array $options = [])
    {
        if (!is_string($bytes)) {
            throw new \InvalidArgumentException('$bytes must be a string');
        }

        $chunkSize = 262144;
        if (isset($options['chunkSize'])) {
            if (!is_int($options['chunkSize']) || $options['chunkSize'] <= 0) {
                throw new \InvalidArgumentException('chunkSize option need to be an integer >= 1');
            }

            $chunkSize = $options['chunkSize'];
        }

        $files = $grid->db->selectCollection($grid->getName());
        $chunks = $grid->chunks;
        $bytesLen = strlen($bytes);
        $newNCount = (int)ceil($bytesLen / $chunkSize);

        for ($n = 0; $n < $newNCount; ++$n) {
            $chunkData = new \MongoBinData(substr($bytes, $n * $chunkSize, $chunkSize), \MongoBinData::BYTE_ARRAY);
            $chunks->update(['files_id' => $id, 'n' => $n], ['$set' => ['data' => $chunkData]], ['upsert' => true]);
        }

        $chunks->remove(['files_id' => $id, 'n' => ['$gte' => $newNCount]]);

        if (!isset($modifierDoc['$set'])) {
            $modifierDoc['$set'] = [];
        }

        $modifierDoc['$set'] = [
            'length' => $bytesLen,
            'chunkSize' => $chunkSize,
            'md5' => md5($bytes)
        ] + $modifierDoc['$set'];
        try {
            if ($files->update(['_id' => $id], $modifierDoc)['n'] !== 1) {
                throw new \Exception("_id '{$id}' did not exist to update in gridfs files");
            }
        } catch (\Exception $e) {
            $chunks->remove(['files_id' => $id]);
            throw $e;
        }
    }
}
