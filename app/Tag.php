<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{

    protected $fillable = ['name', 'display_name'];

    protected $hidden = ['created_at', 'updated_at'];

    /**
     * @param string $name
     * @param string|null $displayName
     * @return Tag
     */
    public static function makeTag(string $name, string $displayName = null)
    {
        $providedDisplayName = $displayName;
        $displayName = $displayName ?? implode(' ', array_map('ucfirst', explode('_', $name)));
        $tag = Tag::where('name', $name)->first();
        if ($tag) {
            if (!$tag->display_name && !$providedDisplayName) {

                $tag->display_name = $displayName;
                $tag->save();

            } else if ($providedDisplayName) {

                $tag->display_name = $providedDisplayName;
                $tag->save();

            }
        } else {
            $record = ['name' => $name, 'display_name' => $providedDisplayName ?? $displayName];
            $tag = Tag::create($record);
        }

        return $tag;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'tag_task');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_tags');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function banners()
    {
        return $this->belongsToMany(Banner::class, 'banner_tags');
    }
}
