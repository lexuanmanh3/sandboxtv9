<?php

namespace App\Services\Authorization;

/** Lọc menu nhiều tầng để không hiển thị quyền lá bị từ chối hoặc menu cha rỗng. */
class MenuBuilder
{
    public function build(array $items, iterable $permissionCodes): array
    {
        $allowed = array_fill_keys(is_array($permissionCodes) ? $permissionCodes : iterator_to_array($permissionCodes), true);

        return collect($items)->sortBy('order')->map(function (array $item) use ($allowed) {
            $children = $this->build($item['children'] ?? [], array_keys($allowed));
            $hasChildrenDefinition = array_key_exists('children', $item);
            $isAllowedLeaf = isset($allowed[$item['permission'] ?? '']);

            if (($hasChildrenDefinition && $children === []) || (! $hasChildrenDefinition && ! $isAllowedLeaf)) {
                return null;
            }

            $item['children'] = $children;
            return $item;
        })->filter()->values()->all();
    }
}
