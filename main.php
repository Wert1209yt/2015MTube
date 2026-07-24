<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// =============================================
// Configuration
// =============================================
define('YOUTUBE_API_KEY', 'Your API key'); // Your API key
define('CACHE_DIR', __DIR__ . '/cache/'); // cache
define('CACHE_TTL', 900);

// =============================================
// Helpers
// =============================================

function youtube_api($endpoint, $params) {
    $params['key'] = YOUTUBE_API_KEY;
    $url = 'https://www.googleapis.com/youtube/v3/' . $endpoint . '?' . http_build_query($params);
    $response = file_get_contents($url);
    if ($response === false) return ['error' => 'API request failed'];
    return json_decode($response, true);
}

function cache_get($key) {
    $file = CACHE_DIR . md5($key) . '.json';
    if (file_exists($file) && (time() - filemtime($file) < CACHE_TTL)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}
function cache_set($key, $data) {
    $file = CACHE_DIR . md5($key) . '.json';
    file_put_contents($file, json_encode($data));
}

function iso8601_to_seconds($iso) {
    preg_match('/PT(\d+H)?(\d+M)?(\d+S)?/', $iso, $matches);
    $h = (int) str_replace('H', '', $matches[1] ?? 0);
    $m = (int) str_replace('M', '', $matches[2] ?? 0);
    $s = (int) str_replace('S', '', $matches[3] ?? 0);
    return $h * 3600 + $m * 60 + $s;
}

function format_duration($seconds) {
    if ($seconds <= 0) return 'LIVE';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
}

function fmt($text) {
    return ['item_type' => 'formatted_string', 'runs' => [['text' => (string)$text]]];
}

function random_ctp() {
    return 'IhMIzezrzOjgxQIVw7R+Ch38bgAL';
}

// =============================================
// Objects builders
// =============================================

function build_compact_video($videoId, $title, $channelTitle, $thumbUrl, $durationSec, $viewCount, $isLive = false, $isWatched = false) {
    $durationStr = $isLive ? 'LIVE' : format_duration($durationSec);
    $viewCountStr = $viewCount ? number_format($viewCount) . ' views' : '';
    $badges = [];
    if ($isLive) {
        $badges[] = ['item_type' => 'badge', 'text' => fmt('LIVE')];
    }
    return [
        'item_type' => 'compact_video',
        'encrypted_id' => $videoId,
        'title' => fmt($title),
        'short_byline' => fmt($channelTitle),
        'view_count' => fmt($viewCountStr),
        'length' => [
            'item_type' => 'formatted_string',
            'accessibility' => ['label' => $durationStr],
            'runs' => [['text' => $durationStr]]
        ],
        'length_seconds' => $durationSec, // для плеера
        'thumbnail_info' => [
            'url' => $thumbUrl,
            'width' => 128, 'height' => 1600,
            'thumb_width' => 120, 'thumb_height' => 72,
            'posy' => 0, 'posx' => 0, 'stitched' => 1
        ],
        'endpoint' => ['click_tracking_params' => random_ctp(), 'url' => '/watch?v=' . $videoId],
        'is_watched' => $isWatched,
        'badges' => $badges,
        'menu' => [
            'items' => [[
                'item_type' => 'menu_service_item',
                'service_endpoint' => [
                    'click_tracking_params' => random_ctp(),
                    '_service_endpoint_type' => 'playlist_edit',
                    'params' => [
                        'playlist_id' => 'WL',
                        'video_id' => $videoId,
                        'session_token' => 'QUFFLUhqblY1SFFiWEdCMlFTeEJCWm4xLXlFOUV6NnVyZ3xBQ3Jtc0trRGVUSEwwUHBlX0swR21IUHhjcjljaGI0ajVtODVpLW02dHN2SGdlLWVaaHJ6SDg4ZDhsNzdtU0NqX3JZOXYzUDd2ZWNGMk83UGIwRWE1eW9vcERFYnpXV3VfUk13SzhJOXAwWE53dENuSmZrcXhSZw=='
                    ],
                    'url' => '/playlist_edit_service?action_add_video=1',
                    'actions' => [['action' => 1]]
                ],
                'text' => fmt('Add to Watch Later')
            ]]
        ],
        'upcoming_event_data' => ['start_time' => 0]
    ];
}

function build_compact_channel($channelId, $title, $thumbUrl, $subscriberCount, $videoCount) {
    return [
        'item_type' => 'compact_channel',
        'channel_id' => $channelId,
        'title' => fmt($title),
        'thumbnail' => ['url' => $thumbUrl],
        'subscriber_count' => fmt(number_format($subscriberCount) . ' subscribers'),
        'video_count' => fmt(number_format($videoCount) . ' videos'),
        'endpoint' => ['click_tracking_params' => random_ctp(), 'url' => '/channel/' . $channelId]
    ];
}

function build_compact_playlist($playlistId, $title, $channelTitle, $thumbUrl, $videoCount) {
    return [
        'item_type' => 'compact_playlist',
        'playlist_id' => $playlistId,
        'title' => fmt($title),
        'owner' => fmt($channelTitle),
        'video_count_short' => fmt((string)$videoCount),
        'thumbnail_info' => [
            'url' => $thumbUrl,
            'width' => 320,
            'height' => 180,
            'thumb_width' => 320,
            'thumb_height' => 180,
            'posy' => 0,
            'posx' => 0,
            'stitched' => 0
        ],
        'endpoint' => [
            'click_tracking_params' => random_ctp(),
            '_navigation_endpoint_type' => 'generic_url',
            'url' => '/playlist?list=' . $playlistId
        ]
    ];
}

function build_shelf($title, $items, $subtitle = '', $endpointUrl = '') {
    return [
        'item_type' => 'shelf',
        'endpoint' => ['click_tracking_params' => random_ctp(), 'url' => $endpointUrl],
        'title' => fmt($title),
        'thumbnail' => ['url' => ''],
        'content' => [
            'item_type' => 'vertical_list',
            'collapsed_state_button_text' => fmt('More'),
            'expanded_state_button_text' => fmt('Show all'),
            'collapsed_item_count' => 3,
            'items' => $items
        ],
        'subtitle' => fmt($subtitle)
    ];
}

function build_item_section($contents) {
    return ['item_type' => 'item_section', 'contents' => $contents, 'continuations' => []];
}

function build_section_list($contents, $continuations = []) {
    return ['item_type' => 'section_list', 'contents' => $contents, 'continuations' => $continuations];
}

function build_tab($title, $selected, $content, $url = '/') {
    return [
        'item_type' => 'tab',
        'endpoint' => ['click_tracking_params' => random_ctp(), 'url' => $url],
        'selected' => $selected,
        'title' => $title,
        'content' => $content
    ];
}

function build_playlist_video($videoId, $title, $channelTitle, $thumbUrl, $durationSec, $viewCount, $isLive = false, $playlistId = '') {
    $durationStr = $isLive ? 'LIVE' : format_duration($durationSec);
    return [
        'item_type' => 'playlist_video',
        'video_id' => $videoId,
        'title' => fmt($title),
        'short_byline' => fmt($channelTitle),
        'length' => [
            'item_type' => 'formatted_string',
            'accessibility' => ['label' => $durationStr],
            'runs' => [['text' => $durationStr]]
        ],
        'thumbnail' => [
            'url' => $thumbUrl,
            'width' => 320,
            'height' => 180,
            'thumb_width' => 320,
            'thumb_height' => 180,
            'posy' => 0,
            'posx' => 0,
            'stitched' => 0
        ],
        'endpoint' => [
            'click_tracking_params' => random_ctp(),
            '_navigation_endpoint_type' => 'generic_url',
            'url' => '/watch?list=' . $playlistId . '&v=' . $videoId
        ],
        'is_watched' => false,
        'set_video_id' => '',
        'annotation' => '',
        'bottom_standalone_badge' => null,
        'menu' => [
            'items' => [[
                'item_type' => 'menu_service_item',
                'service_endpoint' => [
                    'click_tracking_params' => random_ctp(),
                    '_service_endpoint_type' => 'playlist_edit',
                    'params' => [
                        'playlist_id' => 'WL',
                        'video_id' => $videoId,
                        'session_token' => 'QUFFLUhqbVg5ZHZkSmpIZjY0bVJPYThZZzVBZTRjNzVGQXxBQ3Jtc0tsNnRjVXZKQVlJcjdFWDQyanljZTlQeXFzVVlPcVRKOXVfUFBEeUozZGdwdV9VZzhRUXFwUnJCUUJrb1J4XzF5WDFvSGNaSUx4NmdTWUtlQUlVMThpWUlkalpRd2RPTTdSRGZxY3IyekowNFg0ZkpNREQ2cDdCQzdsM1lsVU5qbm1PNWFtWl9ONHhhSjhGemM4QUhlWWJXWEd6cXc='
                    ],
                    'url' => '/playlist_edit_service?action_add_video=1',
                    'actions' => [['action' => 1]]
                ],
                'text' => fmt('Add to Watch Later')
            ]]
        ]
    ];
}

// =============================================
// Controllers
// =============================================

/** /feed */
function handle_feed($params) {
    $cacheKey = 'feed_' . ($params['ctoken'] ?? '');
    if ($cached = cache_get($cacheKey)) return $cached;

    $shelves = [];

    $popular = youtube_api('search', [
        'part' => 'snippet',
        'type' => 'video',
        'order' => 'viewCount',
        'maxResults' => 10,
        'regionCode' => 'US',
        'publishedAfter' => date('Y-m-d\TH:i:s\Z', strtotime('-7 days'))
    ]);

    if (!isset($popular['error']) && !empty($popular['items'])) {
        $videos = [];
        $videoIds = array_map(function($i) { return $i['id']['videoId']; }, $popular['items']);
        // Получаем детали только если есть ID
        if (!empty($videoIds)) {
            $details = youtube_api('videos', ['part' => 'contentDetails,statistics,snippet', 'id' => implode(',', $videoIds)]);
            $vidDetails = [];
            foreach ($details['items'] ?? [] as $v) {
                $vidDetails[$v['id']] = [
                    'duration' => iso8601_to_seconds($v['contentDetails']['duration']),
                    'viewCount' => $v['statistics']['viewCount'] ?? 0,
                    'isLive' => ($v['snippet']['liveBroadcastContent'] ?? 'none') === 'live'
                ];
            }
            foreach ($popular['items'] as $item) {
                $vid = $item['id']['videoId'];
                $d = $vidDetails[$vid] ?? ['duration' => 0, 'viewCount' => 0, 'isLive' => false];
                $videos[] = build_compact_video(
                    $vid,
                    $item['snippet']['title'],
                    $item['snippet']['channelTitle'],
                    $item['snippet']['thumbnails']['default']['url'] ?? '',
                    $d['duration'],
                    $d['viewCount'],
                    $d['isLive']
                );
            }
        }
        if (!empty($videos)) {
            $shelves[] = build_shelf('Popular Right Now', $videos, '', '/playlist?list=PLrEnWoR732-B41pmZfHgpOLjpKChWaA5l');
        }
    }

    $music = youtube_api('search', [
        'part' => 'snippet',
        'type' => 'video',
        'q' => 'music video',
        'order' => 'date',
        'maxResults' => 10,
        'regionCode' => 'US'
    ]);
    if (!isset($music['error']) && !empty($music['items'])) {
        $videos = [];
        $videoIds = array_map(function($i) { return $i['id']['videoId']; }, $music['items']);
        if (!empty($videoIds)) {
            $details = youtube_api('videos', ['part' => 'contentDetails,statistics,snippet', 'id' => implode(',', $videoIds)]);
            $vidDetails = [];
            foreach ($details['items'] ?? [] as $v) {
                $vidDetails[$v['id']] = [
                    'duration' => iso8601_to_seconds($v['contentDetails']['duration']),
                    'viewCount' => $v['statistics']['viewCount'] ?? 0,
                    'isLive' => ($v['snippet']['liveBroadcastContent'] ?? 'none') === 'live'
                ];
            }
            foreach ($music['items'] as $item) {
                $vid = $item['id']['videoId'];
                $d = $vidDetails[$vid] ?? ['duration' => 0, 'viewCount' => 0, 'isLive' => false];
                $videos[] = build_compact_video(
                    $vid,
                    $item['snippet']['title'],
                    $item['snippet']['channelTitle'],
                    $item['snippet']['thumbnails']['default']['url'] ?? '',
                    $d['duration'],
                    $d['viewCount'],
                    $d['isLive']
                );
            }
        }
        if (!empty($videos)) {
            $shelves[] = build_shelf('Just-Released Music Videos', $videos, 'Avicii leads the pack...', '/playlist?list=PLrEnWoR732-D67iteOI6DPdJH1opjAuJt');
        }
    }

    if (empty($shelves)) {
        $shelves[] = build_shelf('No videos available', []);
    }

    $section = build_item_section($shelves);
    $continuations = [];
    // ... (остальное без изменений)

    $sectionList = build_section_list([$section], $continuations);

    $response = [
        'result' => 'ok',
        'conn' => 'wifi',
        'build_signature' => 'en:0',
        'signed_in_username' => '',
        'build_id' => 0,
        'content' => [
            'header' => ['title' => fmt('What to Watch')],
            'feed_name' => 'what_to_watch',
            'single_column_browse_results' => [
                'tabs' => [build_tab('Home', true, $sectionList, '/')]
            ],
            'survey' => null
        ]
    ];
    cache_set($cacheKey, $response);
    return $response;
}

/** /results */
function handle_results($params) {
    $query = $params['q'] ?? '';
    $searchType = $params['search_type'] ?? 'by_search';
    $pageToken = isset($params['ctoken']) ? base64_decode($params['ctoken']) : null;

    $cacheKey = 'results_' . md5($query . $searchType . $pageToken);
    if ($cached = cache_get($cacheKey)) return $cached;

    $items = [];
    $nextPageToken = null;

    if ($searchType == 'by_search') {
        // Поиск видео
        $searchParams = [
            'part' => 'snippet',
            'type' => 'video',
            'q' => $query,
            'maxResults' => 20,
            'regionCode' => 'US'
        ];
        if ($pageToken) $searchParams['pageToken'] = $pageToken;
        $data = youtube_api('search', $searchParams);
        if (isset($data['error'])) return ['result' => 'error', 'message' => 'API error'];
        $itemsData = $data['items'] ?? [];
        $nextPageToken = $data['nextPageToken'] ?? null;
        $videoIds = array_map(function($i) { return $i['id']['videoId']; }, $itemsData);
        $videoDetails = [];
        if (!empty($videoIds)) {
            $details = youtube_api('videos', ['part' => 'contentDetails,statistics,snippet', 'id' => implode(',', $videoIds)]);
            foreach ($details['items'] ?? [] as $v) {
                $videoDetails[$v['id']] = [
                    'duration' => iso8601_to_seconds($v['contentDetails']['duration']),
                    'viewCount' => $v['statistics']['viewCount'] ?? 0,
                    'isLive' => ($v['snippet']['liveBroadcastContent'] ?? 'none') === 'live'
                ];
            }
        }
        $items = [];
        foreach ($itemsData as $item) {
            $vid = $item['id']['videoId'];
            $d = $videoDetails[$vid] ?? ['duration' => 0, 'viewCount' => 0, 'isLive' => false];
            $items[] = build_compact_video(
                $vid,
                $item['snippet']['title'],
                $item['snippet']['channelTitle'],
                $item['snippet']['thumbnails']['default']['url'] ?? '',
                $d['duration'],
                $d['viewCount'],
                $d['isLive']
            );
        }
    } elseif ($searchType == 'search_users') {
        $searchParams = [
            'part' => 'snippet',
            'type' => 'channel',
            'q' => $query,
            'maxResults' => 20,
            'regionCode' => 'US'
        ];
        if ($pageToken) $searchParams['pageToken'] = $pageToken;
        $data = youtube_api('search', $searchParams);
        if (isset($data['error'])) return ['result' => 'error', 'message' => 'API error'];
        $itemsData = $data['items'] ?? [];
        $nextPageToken = $data['nextPageToken'] ?? null;
        $channelIds = array_map(function($i) { return $i['id']['channelId']; }, $itemsData);
        $channelStats = [];
        if (!empty($channelIds)) {
            $stats = youtube_api('channels', ['part' => 'statistics', 'id' => implode(',', $channelIds)]);
            foreach ($stats['items'] ?? [] as $ch) {
                $channelStats[$ch['id']] = [
                    'subscriberCount' => $ch['statistics']['subscriberCount'] ?? 0,
                    'videoCount' => $ch['statistics']['videoCount'] ?? 0
                ];
            }
        }
        $items = [];
        foreach ($itemsData as $item) {
            $cid = $item['id']['channelId'];
            $s = $channelStats[$cid] ?? ['subscriberCount' => 0, 'videoCount' => 0];
            $items[] = build_compact_channel(
                $cid,
                $item['snippet']['title'],
                $item['snippet']['thumbnails']['default']['url'] ?? '',
                $s['subscriberCount'],
                $s['videoCount']
            );
        }
    } elseif ($searchType == 'search_playlists') {
        $searchParams = [
            'part' => 'snippet',
            'type' => 'playlist',
            'q' => $query,
            'maxResults' => 20,
            'regionCode' => 'US'
        ];
        if ($pageToken) $searchParams['pageToken'] = $pageToken;
        $data = youtube_api('search', $searchParams);
        if (isset($data['error'])) return ['result' => 'error', 'message' => 'API error'];
        $itemsData = $data['items'] ?? [];
        $nextPageToken = $data['nextPageToken'] ?? null;
        $items = [];
        foreach ($itemsData as $item) {
            $items[] = build_compact_playlist(
                $item['id']['playlistId'],
                $item['snippet']['title'],
                $item['snippet']['channelTitle'],
                $item['snippet']['thumbnails']['default']['url'] ?? '',
                $item['snippet']['videoCount'] ?? 0
            );
        }
    }

    $section = build_item_section($items);
    $continuations = [];
    if ($nextPageToken) {
        $continuations[] = [
            'item_type' => 'next_continuation_data',
            'click_tracking_params' => random_ctp(),
            'continuation' => base64_encode($nextPageToken)
        ];
    }
    $sectionList = build_section_list([$section], $continuations);

    $response = [
        'result' => 'ok',
        'conn' => 'wifi',
        'build_signature' => 'en:0',
        'signed_in_username' => '',
        'build_id' => 0,
        'content' => [
            'search_results' => $sectionList,
            'pyv' => ['request_afc' => false]
        ]
    ];
    cache_set($cacheKey, $response);
    return $response;
}

// =============================================
// Channel controller, WIP
// =============================================
function handle_browse($params) {
    $browseId = $params['browse_id'] ?? '';
    if (!$browseId) {
        return ['result' => 'error', 'message' => 'Missing browse_id'];
    }

    $cacheKey = 'browse_' . $browseId . '_' . ($params['tab'] ?? 'videos');
    if ($cached = cache_get($cacheKey)) return $cached;

    $channelData = youtube_api('channels', [
        'part' => 'snippet,statistics,brandingSettings',
        'id' => $browseId
    ]);
    if (empty($channelData['items'])) {
        return ['result' => 'error', 'message' => 'Channel not found'];
    }
    $channel = $channelData['items'][0];
    $snippet = $channel['snippet'];
    $statistics = $channel['statistics'];
    $branding = $channel['brandingSettings']['channel'] ?? [];

    $selectedTab = $params['tab'] ?? 'videos';
    $subscriberCount = (int)($statistics['subscriberCount'] ?? 0);
    $viewCount = (int)($statistics['viewCount'] ?? 0);
    $videoCount = (int)($statistics['videoCount'] ?? 0);

    // --- Получение видео канала ---
    $searchParams = [
        'part' => 'snippet',
        'channelId' => $browseId,
        'type' => 'video',
        'order' => 'date',
        'maxResults' => 20
    ];
    if (isset($params['action_continuation'])) {
        $searchParams['pageToken'] = base64_decode($params['action_continuation']);
    }
    $searchData = youtube_api('search', $searchParams);
    $videoItems = $searchData['items'] ?? [];
    $nextPageToken = $searchData['nextPageToken'] ?? null;

    $videoIds = array_filter(array_map(function($i) { 
        return $i['id']['videoId'] ?? null; 
    }, $videoItems));
    
    $videoDetails = [];
    if (!empty($videoIds)) {
        $details = youtube_api('videos', [
            'part' => 'contentDetails,statistics',
            'id' => implode(',', $videoIds)
        ]);
        foreach ($details['items'] ?? [] as $v) {
            $videoDetails[$v['id']] = [
                'duration' => iso8601_to_seconds($v['contentDetails']['duration']),
                'viewCount' => $v['statistics']['viewCount'] ?? 0
            ];
        }
    }

    $videos = [];
    foreach ($videoItems as $item) {
        $vid = $item['id']['videoId'] ?? null;
        if (!$vid) continue;
        $d = $videoDetails[$vid] ?? ['duration' => 0, 'viewCount' => 0];
        $thumbnails = $item['snippet']['thumbnails'] ?? [];
        $thumbUrl = $thumbnails['default']['url'] ?? $thumbnails['medium']['url'] ?? '';
        $videos[] = build_compact_video(
            $vid,
            $item['snippet']['title'] ?? 'Untitled',
            $item['snippet']['channelTitle'] ?? 'Unknown',
            $thumbUrl,
            $d['duration'],
            $d['viewCount'],
            false
        );
    }

    if (empty($videos)) {
        $videos[] = [
            'item_type' => 'shelf',
            'title' => fmt('No videos'),
            'subtitle' => fmt('This channel has no videos yet'),
            'content' => [
                'item_type' => 'vertical_list',
                'collapsed_item_count' => 1,
                'items' => []
            ]
        ];
    }

    $videoSection = [
        'item_type' => 'item_section',
        'contents' => $videos,
        'continuations' => []
    ];
    if ($nextPageToken) {
        $videoSection['continuations'][] = [
            'item_type' => 'next_continuation_data',
            'click_tracking_params' => random_ctp(),
            'continuation' => base64_encode($nextPageToken)
        ];
    }
    $videoContent = [
        'item_type' => 'section_list',
        'contents' => [$videoSection],
        'continuations' => []
    ];

    $aboutContent = null;
    if ($selectedTab === 'about' || $selectedTab === 'channels') {
        $aboutItems = [];
        if (!empty($snippet['description'])) {
            $aboutItems[] = [
                'item_type' => 'channel_about_metadata',
                'description' => fmt($snippet['description']),
                'subscriber_count_text' => fmt(number_format($subscriberCount) . ' subscribers'),
                'view_count_text' => fmt(number_format($viewCount) . ' views'),
                'joined_date_text' => fmt('Joined ' . date('M d, Y', strtotime($snippet['publishedAt']))),
                'country' => $snippet['country'] ?? '',
                'primary_links' => [
                    [
                        'title' => 'YouTube Channel',
                        'navigation_endpoint' => [
                            'url' => '/channel/' . $browseId,
                            'click_tracking_params' => random_ctp()
                        ]
                    ]
                ],
                'show_metadata' => []
            ];
        }
        $aboutSection = [
            'item_type' => 'item_section',
            'contents' => $aboutItems,
            'continuations' => []
        ];
        $aboutContent = [
            'item_type' => 'section_list',
            'contents' => [$aboutSection],
            'continuations' => []
        ];
    }

    $playlistItems = [];
    $playlistsData = youtube_api('playlists', [
        'part' => 'snippet,contentDetails',
        'channelId' => $browseId,
        'maxResults' => 20
    ]);
    if (!empty($playlistsData['items'])) {
        foreach ($playlistsData['items'] as $pl) {
            $plVideoCount = $pl['contentDetails']['itemCount'] ?? 0;
            if ($plVideoCount == 0) {
                $itemsData = youtube_api('playlistItems', [
                    'part' => 'snippet',
                    'playlistId' => $pl['id'],
                    'maxResults' => 1
                ]);
                $plVideoCount = $itemsData['pageInfo']['totalResults'] ?? 0;
            }
            $playlistItems[] = build_compact_playlist(
                $pl['id'],
                $pl['snippet']['title'],
                $snippet['title'],
                $pl['snippet']['thumbnails']['default']['url'] ?? '',
                $plVideoCount
            );
        }
    }
    $playlistContent = null;
    if (!empty($playlistItems)) {
        $playlistSection = [
            'item_type' => 'item_section',
            'contents' => $playlistItems,
            'continuations' => []
        ];
        $playlistContent = [
            'item_type' => 'section_list',
            'contents' => [$playlistSection],
            'continuations' => []
        ];
    }

    $tabs = [];
    $tabs[] = [
        'title' => 'Videos',
        'selected' => ($selectedTab === 'videos'),
        'content' => $videoContent,
        'endpoint' => [
            'click_tracking_params' => random_ctp(),
            'url' => '/browse?browse_id=' . $browseId . '&tab=videos'
        ]
    ];

    if (!empty($playlistItems)) {
        $tabs[] = [
            'title' => 'Playlists',
            'selected' => ($selectedTab === 'playlists'),
            'content' => $playlistContent,
            'endpoint' => [
                'click_tracking_params' => random_ctp(),
                'url' => '/browse?browse_id=' . $browseId . '&tab=playlists'
            ]
        ];
    }

    if (!empty($snippet['description'])) {
        $tabs[] = [
            'title' => 'About',
            'selected' => ($selectedTab === 'about'),
            'content' => $aboutContent,
            'endpoint' => [
                'click_tracking_params' => random_ctp(),
                'url' => '/browse?browse_id=' . $browseId . '&tab=about'
            ]
        ];
    }

    // channel header
    $xsrfToken = 'QUFFLUhqa2NMbUdKTDZlZWl4aFBIVnQwN3FyTmc2M0hwQXxBQ3Jtc0trX044MG5iMHl6T0VLU1ljSUJ2dzB5a2ExX0Z2b1M3UjBmcVZpdXNoWVZEbjNQNjJYMUZEalJOOVIzR3RNdzZSQmxtWjBKWHhueDNzZXQ4QVNkcUZaRlg2Vno2TVhIaURwVWlyYmxEbHJYdllqOXRBRjBKc3NRV2lPUTZaZ0MtMjJDVkFXTnJaTllYZVZFNm1fUFNsWk5jbDlUbEE=';

    $header = [
        'item_type' => 'channel_header',
        'channel_id' => $browseId,
        'title' => $snippet['title'],
        'avatar' => [
            'url' => $snippet['thumbnails']['default']['url'] ?? '',
            'width' => 88,
            'height' => 88,
            'posy' => 0,
            'posx' => 0,
            'stitched' => 0,
            'thumb_width' => 88,
            'thumb_height' => 88
        ],
        'banner' => [
            'url' => $branding['bannerExternalUrl'] ?? '',
            'width' => 1280,
            'height' => 270,
            'posy' => 0,
            'posx' => 0,
            'stitched' => 0,
            'thumb_width' => 1280,
            'thumb_height' => 270
        ],

        'channel_url' => '/channel/' . $browseId,
        'banner_image' => $branding['bannerExternalUrl'] ?? '',
        'banner_image_hd' => $branding['bannerExternalUrl'] ?? '',
        'subscriber_count' => $subscriberCount,
        'subscribe_button' => [
            'item_type' => 'subscribe_button',
            'subscribed' => false,
            'channel_id' => $browseId,
            'xsrf_token' => $xsrfToken,
            'subscriber_count_text' => fmt(number_format($subscriberCount)),
            'long_subscriber_count_text' => fmt(number_format($subscriberCount) . ' subscribers'),
            'unsubscribed_button_text' => fmt('Subscribe'),
            'subscribed_button_text' => fmt('Subscribed'),
            'service_endpoints' => [
                [
                    '_service_endpoint_type' => 'subscribe',
                    'url' => '/subscription_service?action_subscribe=1',
                    'click_tracking_params' => random_ctp(),
                    'params' => [
                        'session_token' => $xsrfToken,
                        'channel_ids' => $browseId
                    ]
                ],
                [
                    '_service_endpoint_type' => 'unsubscribe',
                    'url' => '/subscription_service?action_unsubscribe=1',
                    'click_tracking_params' => random_ctp(),
                    'params' => [
                        'session_token' => $xsrfToken,
                        'channel_ids' => $browseId
                    ]
                ]
            ],
            'type' => 'free',
            'style_type' => 'unknown',
            'enabled' => true
        ],
        'video_count_text' => fmt(number_format($videoCount) . ' videos'),
        'visibility' => 'PUBLIC',
        'analytics_id' => '',
        'description' => $snippet['description'] ?? ''
    ];

    // metadata
    $metadata = [
        'tracking_image_url' => '',
        'channel_conversion_url' => '',
        'analytics_id' => ''
    ];

    $response = [
        'result' => 'ok',
        'conn' => 'wifi',
        'build_signature' => 'en:0',
        'signed_in_username' => '',
        'build_id' => 0,
        'content' => [
            'browse_id' => $browseId,
            'header' => $header,
            'metadata' => $metadata,
            'tab_settings' => [
                'available_tabs' => $tabs
            ]
        ]
    ];

    cache_set($cacheKey, $response);
    return $response;
}

function handle_playlist($params) {
    $playlistId = $params['list'] ?? '';
    if (!$playlistId) {
        return ['result' => 'error', 'message' => 'Missing list'];
    }

    $cacheKey = 'playlist_' . $playlistId;
    if ($cached = cache_get($cacheKey)) return $cached;

    $plData = youtube_api('playlists', [
        'part' => 'snippet,contentDetails',
        'id' => $playlistId
    ]);
    if (empty($plData['items'])) {
        return ['result' => 'error', 'message' => 'Playlist not found'];
    }
    $playlist = $plData['items'][0];
    $plSnippet = $playlist['snippet'];
    $plContentDetails = $playlist['contentDetails'];

    $itemsData = youtube_api('playlistItems', [
        'part' => 'snippet',
        'playlistId' => $playlistId,
        'maxResults' => 50
    ]);
    $videoItems = $itemsData['items'] ?? [];
    $videoIds = array_map(function($i) { return $i['snippet']['resourceId']['videoId']; }, $videoItems);

    $videoDetails = [];
    if (!empty($videoIds)) {
        $details = youtube_api('videos', [
            'part' => 'contentDetails,statistics,snippet',
            'id' => implode(',', $videoIds)
        ]);
        foreach ($details['items'] ?? [] as $v) {
            $videoDetails[$v['id']] = [
                'duration' => iso8601_to_seconds($v['contentDetails']['duration']),
                'viewCount' => $v['statistics']['viewCount'] ?? 0,
                'isLive' => ($v['snippet']['liveBroadcastContent'] ?? 'none') === 'live'
            ];
        }
    }

    $videos = [];
    foreach ($videoItems as $item) {
        $vid = $item['snippet']['resourceId']['videoId'];
        $d = $videoDetails[$vid] ?? ['duration' => 0, 'viewCount' => 0, 'isLive' => false];
        $videos[] = build_playlist_video(
            $vid,
            $item['snippet']['title'],
            $item['snippet']['channelTitle'],
            $item['snippet']['thumbnails']['default']['url'] ?? '',
            $d['duration'],
            $d['viewCount'],
            $d['isLive'],
            $playlistId
        );
    }

    $totalVideos = count($videos);
    $playlistTitle = $plSnippet['title'] ?? 'Untitled playlist';
    $playlistDescription = $plSnippet['description'] ?? '';
    $channelId = $plSnippet['channelId'] ?? '';
    $channelTitle = $plSnippet['channelTitle'] ?? 'Unknown channel';
    $thumbnailUrl = $plSnippet['thumbnails']['default']['url'] ?? '';

    $totalSeconds = array_sum(array_column($videoDetails, 'duration'));
    $totalLength = $totalSeconds > 0 ? format_duration($totalSeconds) : 'N/A';

    $xsrfToken = 'QUFFLUhqa2w3NmFIMU9tSDBYN0h5c3hQcW84WjlzWTNoZ3xBQ3Jtc0tsUm1BcnlTMVR0OFY0dmNlbTljLUNnbnNFaGxueHdhRGpybWVLbUJDLWlLYW9JdGJ6LXRFdUZTWGVwVzN4ak03RWlvVm15dVdoVEhTZV91ckMyc2dQMUtSMzNITUR4d0lXNENFT3A5aEZaamdLWGZPam9RTXdHMkVEU3BNOGJxdzJyazZybzJiMi1mMmtNcGlYaG5DVlBnM0Y1cHc=';

    $playlistHeader = [
        'playlist_id' => $playlistId,
        'title' => fmt($playlistTitle),
        'thumbnail' => [
            'url' => $thumbnailUrl,
            'width' => 320,
            'height' => 180,
            'thumb_width' => 320,
            'thumb_height' => 180,
            'posy' => 0,
            'posx' => 0,
            'stitched' => 0
        ],
        'num_videos_text' => fmt($totalVideos . ' videos'),
        'owner_endpoint' => [
            'url' => '/channel/' . $channelId,
            'click_tracking_params' => random_ctp(),
            '_navigation_endpoint_type' => 'generic_url'
        ],
        'owner_text' => fmt($channelTitle),
        'total_length' => fmt($totalLength),
        'view_count_text' => fmt(''),
        'description' => fmt($playlistDescription),
        'play_endpoint' => [
            'url' => '/playlist?list=' . $playlistId . '&play=1',
            'click_tracking_params' => random_ctp(),
            '_navigation_endpoint_type' => 'generic_url'
        ],
        'share_data' => ['can_share' => true],
        'like_button' => [
            'item_type' => 'like_button',
            'like_count' => 0,
            'dislike_count' => 0,
            'like_count_with_like_text' => fmt('0'),
            'dislike_count_with_dislike_text' => fmt('0'),
            'like_count_text' => fmt('0'),
            'dislike_count_text' => fmt('0'),
            'xsrf_token' => $xsrfToken,
            'like_status' => 'indifferent',
            'target' => [
                'video_id' => '',
                'playlist_id' => $playlistId
            ]
        ],
        'is_editable' => false,
        'privacy' => $plSnippet['privacy'] ?? 'public',
        'owner_thumbnail' => ['url' => ''],
        'banner' => null
    ];

    $videoList = [
        'item_type' => 'playlist_video_list',
        'playlist_id' => $playlistId,
        'contents' => $videos,
        'continuations' => [] // maybe I can add continuation
    ];
    $section = [
        'item_type' => 'item_section',
        'contents' => [$videoList],
        'continuations' => []
    ];
    $sectionList = [
        'item_type' => 'section_list',
        'contents' => [$section],
        'continuations' => []
    ];

    $response = [
        'result' => 'ok',
        'conn' => 'wifi',
        'build_signature' => 'en:0',
        'signed_in_username' => '',
        'build_id' => 0,
        'content' => [
            'playlist_header' => $playlistHeader,
            'section_list' => $sectionList
        ]
    ];

    cache_set($cacheKey, $response);
    return $response;
}

// =============================================
// Watch controller, I'm debugging
// =============================================
function handle_watch($params) {
    $videoId = $params['v'] ?? '';
    if (!$videoId) {
        return ['result' => 'error', 'message' => 'Missing video id'];
    }

    $cacheKey = 'watch_' . $videoId;
    if ($cached = cache_get($cacheKey)) return $cached;

    // Получаем информацию о видео
    $videoData = youtube_api('videos', [
        'part' => 'snippet,statistics,contentDetails',
        'id' => $videoId
    ]);
    if (empty($videoData['items'])) {
        return ['result' => 'error', 'message' => 'Video not found'];
    }
    $video = $videoData['items'][0];
    $snippet = $video['snippet'];
    $statistics = $video['statistics'];
    $contentDetails = $video['contentDetails'];

    $durationSec = iso8601_to_seconds($contentDetails['duration']);
    $isLive = ($snippet['liveBroadcastContent'] ?? 'none') === 'live';
    $viewCount = $statistics['viewCount'] ?? 0;
    $likeCount = $statistics['likeCount'] ?? 0;
    $dislikeCount = $statistics['dislikeCount'] ?? 0;
    $commentCount = isset($statistics['commentCount']) ? (int)$statistics['commentCount'] : 0;

    $channelId = $snippet['channelId'];
    $channelTitle = $snippet['channelTitle'];
    $thumbnailDefault = $snippet['thumbnails']['default']['url'] ?? '';
    $thumbnailHigh = $snippet['thumbnails']['high']['url'] ?? $thumbnailDefault;
    $thumbnailForWatch = '//i.ytimg.com/vi/' . $videoId . '/mqdefault.jpg';

    $relatedParams = [
        'part' => 'snippet',
        'channelId' => $channelId,
        'type' => 'video',
        'order' => 'date',
        'maxResults' => 20
    ];
    $relatedData = youtube_api('search', $relatedParams);
    $relatedVideos = [];
    if (!isset($relatedData['error'])) {
        $videoIds = [];
        $relatedItems = [];
        foreach ($relatedData['items'] as $item) {
            $vid = $item['id']['videoId'];
            if ($vid && $vid != $videoId) {
                $videoIds[] = $vid;
                $relatedItems[$vid] = $item;
            }
        }
        $details = [];
        if (!empty($videoIds)) {
            $detailsData = youtube_api('videos', [
                'part' => 'contentDetails,statistics',
                'id' => implode(',', $videoIds)
            ]);
            foreach ($detailsData['items'] ?? [] as $v) {
                $details[$v['id']] = [
                    'duration' => iso8601_to_seconds($v['contentDetails']['duration']),
                    'viewCount' => $v['statistics']['viewCount'] ?? 0
                ];
            }
        }
        foreach ($relatedItems as $vid => $item) {
            $d = $details[$vid] ?? ['duration' => 0, 'viewCount' => 0];
            $relatedVideos[] = [
                'title' => $item['snippet']['title'],
                'thumbnail_info' => [
                    'url' => $item['snippet']['thumbnails']['default']['url'] ?? '',
                    'posy' => 0, 'posx' => 0,
                    'width' => 128, 'height' => 960,
                    'stitched' => 1,
                    'thumb_width' => 120,
                    'thumb_height' => 72
                ],
                'view_count_text' => $d['viewCount'] ? number_format($d['viewCount']) . ' views' : '',
                'duration' => format_duration($d['duration']),
                'watch_link' => '/watch?v=' . $vid . '&itct=' . random_ctp(),
                'encrypted_id' => $vid,
                'public_name' => $item['snippet']['channelTitle']
            ];
        }
    }

    $xsrfToken = 'QUFFLUhqa2NMbUdKTDZlZWl4aFBIVnQwN3FyTmc2M0hwQXxBQ3Jtc0trX044MG5iMHl6T0VLU1ljSUJ2dzB5a2ExX0Z2b1M3UjBmcVZpdXNoWVZEbjNQNjJYMUZEalJOOVIzR3RNdzZSQmxtWjBKWHhueDNzZXQ4QVNkcUZaRlg2Vno2TVhIaURwVWlyYmxEbHJYdllqOXRBRjBKc3NRV2lPUTZaZ0MtMjJDVkFXTnJaTllYZVZFNm1fUFNsWk5jbDlUbEE=';
    $sentimentToken = 'QUFFLUhqbjk2am9lWVNjQTc5ZVdKSC1OVk1wQWY1VlB0UXxBQ3Jtc0treEVwblNJRFlrQ2xJNGlKM2dpSDFfQThkN18teV9SVFdzT1U5Z1lVQUhzRGlZbzJidl9yb0dnekJEdmFFbXppZWxBOXF3b0tKdm1IcTBDd0pfc3NzajdPRHpTVUh4WjJKS0RqRy1DRm5hVHNLYzVHYVJXRFh5V2JpSjZEVFRnNnlkNWdQZ0RyMzY4TjI3cHdkd25WWTZycFl6Qmc=';

    $commentContinuation = base64_encode(json_encode(['videoId' => $videoId]));

    $videoItem = [
        'item_type' => 'video_main_content',
        'video_id' => $videoId,
        'title' => fmt($snippet['title']),
        'view_count_text' => fmt(number_format($viewCount) . ' views'),
        'short_byline_text' => [
            'item_type' => 'formatted_string',
            'runs' => [
                [
                    'text' => $channelTitle,
                    'endpoint' => [
                        'click_tracking_params' => random_ctp(),
                        'url' => '/channel/' . $channelId
                    ]
                ]
            ]
        ],
        'date_text' => fmt('Published on ' . date('M d, Y', strtotime($snippet['publishedAt']))),
        'description' => fmt($snippet['description'] ?? ''),
        'thumbnail' => [
            'url' => $thumbnailDefault,
            'posy' => 19, 'posx' => 0,
            'stitched' => 0,
            'thumb_width' => 88,
            'thumb_height' => 88
        ],
        'subscribe_button' => [
            'subscribed' => false,
            'channel_id' => $channelId,
            'xsrf_token' => $xsrfToken,
            'subscriber_count_text' => fmt('0'),
            'long_subscriber_count_text' => fmt('0 subscribers'),
            'unsubscribed_button_text' => fmt('Subscribe'),
            'subscribed_button_text' => fmt('Subscribed'),
            'service_endpoints' => [
                [
                    '_service_endpoint_type' => 'subscribe',
                    'url' => '/subscription_service?action_subscribe=1',
                    'click_tracking_params' => random_ctp(),
                    'params' => [
                        'session_token' => $xsrfToken,
                        'channel_ids' => $channelId
                    ]
                ],
                [
                    '_service_endpoint_type' => 'unsubscribe',
                    'url' => '/subscription_service?action_unsubscribe=1',
                    'click_tracking_params' => random_ctp(),
                    'params' => [
                        'session_token' => $xsrfToken,
                        'channel_ids' => $channelId
                    ]
                ]
            ],
            'type' => 'free',
            'style_type' => 'unknown',
            'enabled' => true
        ],
        'like_button' => [
            'item_type' => 'like_button',
            'like_count' => (int)$likeCount,
            'dislike_count' => (int)$dislikeCount,
            'like_count_with_like_text' => fmt($likeCount . ''),
            'dislike_count_with_dislike_text' => fmt($dislikeCount . ''),
            'like_count_text' => fmt($likeCount . ''),
            'dislike_count_text' => fmt($dislikeCount . ''),
            'xsrf_token' => $sentimentToken,
            'like_status' => 'indifferent',
            'target' => [
                'video_id' => $videoId,
                'playlist_id' => ''
            ]
        ],
        'allow_ratings' => true,
        'badges' => [],
        'owner_badges' => []
    ];

    $videoMainContent = [
        'item_type' => 'item_section',
        'contents' => [$videoItem],
        'continuations' => []
    ];

    $response = [
        'result' => 'ok',
        'conn' => 'wifi',
        'build_signature' => 'en:0',
        'signed_in_username' => '',
        'build_id' => 0,
        'content' => [
            'video' => [
                'title' => $snippet['title'],
                'watch_link' => '/watch?v=' . $videoId,
                'length_seconds' => $durationSec,
                'encrypted_id' => $videoId,
                'comment_count' => $commentCount,
                'longform' => $durationSec > 600,
                'thumbnail_for_watch' => $thumbnailForWatch,
                'duration' => format_duration($durationSec)
            ],
            'video_main_content' => $videoMainContent,   // <-- изменено
            'related_videos' => $relatedVideos,
            'comment_section' => [
                'item_type' => 'comment_section',
                'header' => [
                    'item_type' => 'comment_section_header',
                    'count_text' => [
                        'item_type' => 'formatted_string',
                        'runs' => [
                            ['bold' => true, 'text' => 'Comments'],
                            ['text' => ' • ' . $commentCount]
                        ]
                    ]
                ],
                'contents' => [],
                'continuations' => [
                    [
                        'item_type' => 'next_continuation_data',
                        'click_tracking_params' => random_ctp(),
                        'continuation' => $commentContinuation
                    ]
                ]
            ],
            'player_data' => [
                'playability' => 'PLAY_OK',
                'player_type' => 'desktop'
            ],
            'swfcfg' => [
                'html5' => true,
                'args' => [
                    'video_id' => $videoId,
                    'title' => $snippet['title'],
                    'length_seconds' => (string)$durationSec,
                    'view_count' => (string)$viewCount,
                    'author' => $channelTitle,
                    'ucid' => $channelId,
                    'token' => '1',
                    'enablejsapi' => 1,
                    'allow_ratings' => '1',
                    'allow_embed' => '1',
                    'ps' => 'native',
                    'c' => 'MWEB',
                    'hl' => 'en_US',
                    'plid' => 'AAUZQNas01T5hhAJ',
                    'cl' => '96656890',
                    'sourceid' => 'r',
                    'url_encoded_fmt_stream_map' => 'url=http%3A%2F%2Flocalhost%3A8000%2Fstream%3Fv%3D' . $videoId . '&itag=18&type=video%2Fmp4%3B+codecs%3D%22avc1.42001E%2C+mp4a.40.2%22&quality=medium'
                ],
                'assets' => [
                    'js' => 'https://s.ytimg.com/yts/jsbin/html5player-en_US-vflPcWTEd/html5player.js',
                    'css' => 'https://s.ytimg.com/yts/cssbin/www-player-vflu9LRGK.css'
                ],
                'url' => 'http://s.ytimg.com/yts/swfbin/player-vflUp4RcX/watch_as3.swf',
                'params' => [
                    'allowfullscreen' => 'true',
                    'allowscriptaccess' => 'always',
                    'bgcolor' => '#000000'
                ],
                'attrs' => ['id' => 'movie_player'],
                'sts' => 16603,
                'min_version' => '8.0.0'
            ],
            'subscribe_xsrf_token' => $xsrfToken,
            'sentiment_xsrf_token' => $sentimentToken,
            'allow_comments' => true,
            'allow_ratings' => true,
            'should_prompt_merge_identity' => false,
            'next_url' => '/related?ctoken=' . random_ctp(),
            'pyv_content' => [
                'tag_for_child_directed' => false,
                'google_cust_gender' => '',
                'ad_host' => 'ca-host-pub-6085707526598399',
                'pyv_ad_channels' => 'yt_mpvid_prPak-usJH4MheiY+yt_cid_15370938+yt_no_3pas+yt_no_vatt+yt_no_ap+yt_no_isi+yt_no_ic+yt_no_360+ytdevice_2+ytdevicever_html5+afv_user_id_cGlph_maNfKQAXK2SlC7qA+afv_user_truthseekerscanada',
                'ad_host_tier_id' => '6138087',
                'core_dbp' => 'ChZZblNWTnd6SnktaXQzSnFjczlOUi13EAE',
                'show_pyv_in_related' => true,
                'google_cust_age' => ''
            ],
            'ad_instream' => [
                'clickthrough' => '',
                'title' => '',
                'encrypted_id' => '',
                'tracking' => ['impression' => ['http://pubads.g.doubleclick.net/pagead/adview?ai=BtrIEIYSKVYzeLcHZ-QOph5KYAZWs3YEHAAAAEAEgADgAWKWn-_lbYMme_YzkpLAUggEXY2EtcHViLTI2MTQ2NjYyNjE1Nzk3NDGyARh3d3cuZGNsay1kZWZhdWx0LXJlZi5jb226AQlnZnBfaW1hZ2XIAQnaASBodHRwOi8vd3d3LmRjbGstZGVmYXVsdC1yZWYuY29tL8ACAuACAOoCIjQwNjEvbW9iaS55dHB3YXRjaC5nYWRnZXRzYW5kZ2FtZXP4AvzRHpADpAOYA-ADqAMB0ASQTuAEAZAGAaAGINgGBNgHAA&sigh=KUKAh_ZHuI4&cid=5GhZkw&adurl=http://pagead2.googlesyndication.com/pagead/imgad/879366/dot.gif?1149026885']],
                'message' => '',
                'stream_url' => '',
                'companion_image' => '',
                'source' => '',
                'duration' => 0
            ]
        ]
    ];

    cache_set($cacheKey, $response);
    return $response;
}

// =============================================
// Watch comments controller
// =============================================
function handle_watch_comment($params) {
    $ctoken = $params['ctoken'] ?? null;
    if (!$ctoken) {
        return ['result' => 'error', 'message' => 'Missing ctoken'];
    }

    $decoded = base64_decode($ctoken);
    $data = json_decode($decoded, true);
    if (!$data || !isset($data['videoId'])) {
        return ['result' => 'error', 'message' => 'Invalid ctoken'];
    }

    $videoId = $data['videoId'];
    $pageToken = $data['pageToken'] ?? null;

    $commentsData = youtube_api('commentThreads', [
        'part' => 'snippet,replies',
        'videoId' => $videoId,
        'maxResults' => 20,
        'pageToken' => $pageToken
    ]);

    if (isset($commentsData['error'])) {
        return ['result' => 'error', 'message' => 'API error'];
    }

    $items = [];
    foreach ($commentsData['items'] as $thread) {
        $snippet = $thread['snippet'];
        $topComment = $snippet['topLevelComment'];
        $topSnippet = $topComment['snippet'];

        $comment = [
            'item_type' => 'comment',
            'content' => fmt($topSnippet['textDisplay']),
            'like_count' => (int)$topSnippet['likeCount'],
            'published_time' => fmt(format_relative_time($topSnippet['publishedAt'])),
            'author_thumbnail' => [
                'url' => $topSnippet['authorProfileImageUrl'],
                'thumb_width' => 32, 'thumb_height' => 32,
                'posx' => 0, 'posy' => 7, 'stitched' => 0
            ],
            'author' => fmt($topSnippet['authorDisplayName']),
            'author_endpoint' => [
                '_navigation_endpoint_type' => 'generic_url',
                'click_tracking_params' => random_ctp(),
                'url' => '/channel/' . $topSnippet['authorChannelId']['value']
            ],
            'is_liked' => false,
            'action_menu' => null
        ];

        $replies = [];
        if (isset($thread['replies'])) {
            foreach ($thread['replies']['comments'] as $reply) {
                $replySnippet = $reply['snippet'];
                $replies[] = [
                    'item_type' => 'comment',
                    'content' => fmt($replySnippet['textDisplay']),
                    'like_count' => (int)$replySnippet['likeCount'],
                    'published_time' => fmt(format_relative_time($replySnippet['publishedAt'])),
                    'author_thumbnail' => [
                        'url' => $replySnippet['authorProfileImageUrl'],
                        'thumb_width' => 32, 'thumb_height' => 32,
                        'posx' => 0, 'posy' => 7, 'stitched' => 0
                    ],
                    'author' => fmt($replySnippet['authorDisplayName']),
                    'author_endpoint' => [
                        '_navigation_endpoint_type' => 'generic_url',
                        'click_tracking_params' => random_ctp(),
                        'url' => '/channel/' . $replySnippet['authorChannelId']['value']
                    ],
                    'is_liked' => false,
                    'action_menu' => null
                ];
            }
        }

        $replyContinuations = [];

        $threadItem = [
            'item_type' => 'comment_thread',
            'comment' => $comment,
            'replies' => [
                'item_type' => 'comment_replies',
                'continuations' => $replyContinuations,
                'contents' => $replies
            ]
        ];
        $items[] = $threadItem;
    }

    $nextPageToken = $commentsData['nextPageToken'] ?? null;
    $nextContinuation = null;
    if ($nextPageToken) {
        $nextData = ['videoId' => $videoId, 'pageToken' => $nextPageToken];
        $nextContinuation = base64_encode(json_encode($nextData));
    }

    $response = [
        'result' => 'ok',
        'conn' => 'wifi',
        'build_signature' => 'en:0',
        'signed_in_username' => '',
        'build_id' => 0,
        'content' => [
            'continuation_contents' => [
                'item_type' => 'comment_section',
                'header' => [
                    'item_type' => 'comment_section_header',
                    'count_text' => [
                        'item_type' => 'formatted_string',
                        'runs' => [
                            ['text' => 'Comments', 'bold' => true],
                            ['text' => ' • ' . (isset($commentsData['pageInfo']['totalResults']) ? $commentsData['pageInfo']['totalResults'] : count($items))]
                        ]
                    ]
                ],
                'sharebox' => [
                    'item_type' => 'comment_simplebox',
                    'author_thumbnail' => [
                        'url' => '//s.ytimg.com/yts/img/avatar_32-vflI3ugzv.png',
                        'thumb_width' => 32, 'thumb_height' => 32,
                        'posx' => 0, 'posy' => 7, 'stitched' => 0
                    ],
                    'prepare_account_endpoint' => [
                        '_navigation_endpoint_type' => 'generic_url',
                        'click_tracking_params' => random_ctp(),
                        'url' => '/signin?next=%2Fwatch%3Fv%3D' . $videoId . '%26itct%3D' . random_ctp()
                    ],
                    'placeholder_text' => fmt('Add a public comment...')
                ],
                'continuations' => $nextContinuation ? [
                    [
                        'item_type' => 'next_continuation_data',
                        'click_tracking_params' => random_ctp(),
                        'continuation' => $nextContinuation
                    ]
                ] : [],
                'contents' => $items
            ]
        ]
    ];

    return $response;
}

/** but client not using this */
function handle_comment_service($params) {
    $videoId = $params['video_id'] ?? '';
    if (!$videoId) return '<div>No video specified</div>';

    $cacheKey = 'comments_' . $videoId;
    if ($cached = cache_get($cacheKey)) return $cached;

    $comments = youtube_api('commentThreads', ['part' => 'snippet', 'videoId' => $videoId, 'maxResults' => 20]);
    $html = '<div id="comments">';
    foreach ($comments['items'] ?? [] as $thread) {
        $comment = $thread['snippet']['topLevelComment']['snippet'];
        $html .= '<div class="comment"><b>' . htmlspecialchars($comment['authorDisplayName']) . '</b><p>' . htmlspecialchars($comment['textDisplay']) . '</p></div>';
    }
    $html .= '</div>';
    cache_set($cacheKey, $html);
    return $html;
}

// =============================================
// guide
// =============================================
function handle_guide_ajax($params) {
    $cacheKey = 'guide_ajax';
    if ($cached = cache_get($cacheKey)) return $cached;

    // Формируем ответ для гида
    $response = [
        'result' => 'ok',
        'conn' => 'wifi',
        'build_signature' => 'en:0',
        'signed_in_username' => '',
        'build_id' => 0,
        'content' => [
            'innertube_guide_response' => [
                'items' => [
                    // Раздел "Каналы" (подписки)
                    [
                        'item_type' => 'guide_subscriptions_section',
                        'formatted_title' => [
                            'item_type' => 'formatted_string',
                            'runs' => [['text' => 'Subscriptions']]
                        ],
                        'items' => [
                            // Пример канала 1
                            [
                                'item_type' => 'guide_entry',
                                'formatted_title' => [
                                    'item_type' => 'formatted_string',
                                    'runs' => [['text' => 'Popular on YouTube']]
                                ],
                                'navigation_endpoint' => [
                                    'click_tracking_params' => 'IhMIzezrzOjgxQIVw7R+Ch38bgAL',
                                    'url' => '/channel/UCF0pVplsI8R5kcAqgtoRqoA'
                                ],
                                'thumbnail' => [
                                    'url' => '//i.ytimg.com/i/F0pVplsI8R5kcAqgtoRqoA/1.jpg',
                                    'posy' => 0,
                                    'posx' => 0,
                                    'stitched' => 0,
                                    'thumb_width' => 28,
                                    'thumb_height' => 28
                                ]
                            ],
                            // Пример канала 2
                            [
                                'item_type' => 'guide_entry',
                                'formatted_title' => [
                                    'item_type' => 'formatted_string',
                                    'runs' => [['text' => 'Music']]
                                ],
                                'navigation_endpoint' => [
                                    'click_tracking_params' => 'IhMIzezrzOjgxQIVw7R+Ch38bgAL',
                                    'url' => '/channel/UC-9-kyTW8ZkZNDHQJ6FgpwQ'
                                ],
                                'thumbnail' => [
                                    'url' => '//i.ytimg.com/i/-9-kyTW8ZkZNDHQJ6FgpwQ/1.jpg',
                                    'posy' => 0,
                                    'posx' => 0,
                                    'stitched' => 0,
                                    'thumb_width' => 28,
                                    'thumb_height' => 28
                                ]
                            ],

                            [
                                'item_type' => 'guide_entry',
                                'formatted_title' => [
                                    'item_type' => 'formatted_string',
                                    'runs' => [['text' => 'Sports']]
                                ],
                                'navigation_endpoint' => [
                                    'click_tracking_params' => 'IhMIzezrzOjgxQIVw7R+Ch38bgAL',
                                    'url' => '/channel/UCEgdi0XIXXZ-qJOFPf4JSKw'
                                ],
                                'thumbnail' => [
                                    'url' => '//i.ytimg.com/i/Egdi0XIXXZ-qJOFPf4JSKw/1.jpg',
                                    'posy' => 0,
                                    'posx' => 0,
                                    'stitched' => 0,
                                    'thumb_width' => 28,
                                    'thumb_height' => 28
                                ]
                            ],
                            // Пример канала 4
                            [
                                'item_type' => 'guide_entry',
                                'formatted_title' => [
                                    'item_type' => 'formatted_string',
                                    'runs' => [['text' => 'Gaming']]
                                ],
                                'navigation_endpoint' => [
                                    'click_tracking_params' => 'IhMIzezrzOjgxQIVw7R+Ch38bgAL',
                                    'url' => '/channel/UCOpNcN46UbXVtpKMrmU4Abg'
                                ],
                                'thumbnail' => [
                                    'url' => '//i.ytimg.com/i/OpNcN46UbXVtpKMrmU4Abg/1.jpg',
                                    'posy' => 0,
                                    'posx' => 0,
                                    'stitched' => 0,
                                    'thumb_width' => 28,
                                    'thumb_height' => 28
                                ]
                            ]
                        ]
                    ],
                    // Раздел "Главное меню"
                    [
                        'item_type' => 'guide_section',
                        'formatted_title' => [
                            'item_type' => 'formatted_string',
                            'runs' => [['text' => '']]
                        ],
                        'items' => [
                            [
                                'item_type' => 'guide_collection_entry',
                                'formatted_title' => [
                                    'item_type' => 'formatted_string',
                                    'runs' => [['text' => 'Home']]
                                ],
                                'navigation_endpoint' => [
                                    'click_tracking_params' => 'IhMIzezrzOjgxQIVw7R+Ch38bgAL',
                                    'url' => '/'
                                ],
                                'thumbnail' => [
                                    'url' => '',
                                    'posy' => 0,
                                    'posx' => 0,
                                    'stitched' => 0,
                                    'thumb_width' => 28,
                                    'thumb_height' => 28
                                ],
                                'icon' => [
                                    'sprite_name' => 'tab_home_selected'
                                ]
                            ],
                            [
                                'item_type' => 'guide_collection_entry',
                                'formatted_title' => [
                                    'item_type' => 'formatted_string',
                                    'runs' => [['text' => 'Trending']]
                                ],
                                'navigation_endpoint' => [
                                    'click_tracking_params' => 'IhMIzezrzOjgxQIVw7R+Ch38bgAL',
                                    'url' => '/feed/trending'
                                ],
                                'thumbnail' => [
                                    'url' => '',
                                    'posy' => 0,
                                    'posx' => 0,
                                    'stitched' => 0,
                                    'thumb_width' => 28,
                                    'thumb_height' => 28
                                ],
                                'icon' => [
                                    'sprite_name' => 'tab_trending'
                                ]
                            ],
                            [
                                'item_type' => 'guide_collection_entry',
                                'formatted_title' => [
                                    'item_type' => 'formatted_string',
                                    'runs' => [['text' => 'Subscriptions']]
                                ],
                                'navigation_endpoint' => [
                                    'click_tracking_params' => 'IhMIzezrzOjgxQIVw7R+Ch38bgAL',
                                    'url' => '/feed/subscriptions'
                                ],
                                'thumbnail' => [
                                    'url' => '',
                                    'posy' => 0,
                                    'posx' => 0,
                                    'stitched' => 0,
                                    'thumb_width' => 28,
                                    'thumb_height' => 28
                                ],
                                'icon' => [
                                    'sprite_name' => 'tab_subscriptions'
                                ]
                            ],
                            [
                                'item_type' => 'guide_collection_entry',
                                'formatted_title' => [
                                    'item_type' => 'formatted_string',
                                    'runs' => [['text' => 'History']]
                                ],
                                'navigation_endpoint' => [
                                    'click_tracking_params' => 'IhMIzezrzOjgxQIVw7R+Ch38bgAL',
                                    'url' => '/feed/history'
                                ],
                                'thumbnail' => [
                                    'url' => '',
                                    'posy' => 0,
                                    'posx' => 0,
                                    'stitched' => 0,
                                    'thumb_width' => 28,
                                    'thumb_height' => 28
                                ],
                                'icon' => [
                                    'sprite_name' => 'watch_history'
                                ]
                            ],
                            [
                                'item_type' => 'guide_collection_entry',
                                'formatted_title' => [
                                    'item_type' => 'formatted_string',
                                    'runs' => [['text' => 'Watch Later']]
                                ],
                                'navigation_endpoint' => [
                                    'click_tracking_params' => 'IhMIzezrzOjgxQIVw7R+Ch38bgAL',
                                    'url' => '/playlist?list=WL'
                                ],
                                'thumbnail' => [
                                    'url' => '',
                                    'posy' => 0,
                                    'posx' => 0,
                                    'stitched' => 0,
                                    'thumb_width' => 28,
                                    'thumb_height' => 28
                                ],
                                'icon' => [
                                    'sprite_name' => 'watch_later'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    cache_set($cacheKey, $response);
    return $response;
}

// =============================================
// Helper for time
// =============================================
function format_relative_time($publishedAt) {
    $now = new DateTime();
    $date = new DateTime($publishedAt);
    $diff = $now->diff($date);
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    } elseif ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    } elseif ($diff->d > 0) {
        if ($diff->d >= 7) {
            $weeks = floor($diff->d / 7);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        }
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}

function handle_generic() {
    return ['result' => 'ok', 'conn' => 'wifi', 'build_signature' => 'en:0', 'signed_in_username' => '', 'build_id' => 0, 'content' => []];
}

// =============================================
// Router
// =============================================
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = $_GET;
$response = null;

if (strpos($path, '/channel/') === 0) {
    $parts = explode('/', trim($path, '/'));
    $channelId = $parts[1] ?? '';
    if ($channelId) {
        $_GET['browse_id'] = $channelId;
        $response = handle_browse($_GET);
        header('Content-Type: application/json; charset=utf-8');
        echo ")]}'" . json_encode($response);
        exit;
    }
}

switch ($path) {
    case '/':
        readfile('index.html');
        break;
    case '/feed':
        $response = handle_feed($params);
        break;
    case '/results':
        $response = handle_results($params);
        break;
    case '/browse':
        $response = handle_browse($params);
        break;
    case '/playlist':
        $response = handle_playlist($params);
        break;
    case '/watch':
        $response = handle_watch($params);
        break;
    case '/watch_comment':   // <-- НОВЫЙ ЭНДПОИНТ
        $response = handle_watch_comment($params);
        break;
    case '/comment_service': // если используется старый способ (iframe)
        $html = handle_comment_service($params);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    case '/guide_ajax':
        $response = handle_guide_ajax($params);
        break;
    case '/stream':
         require 'stream.php';
         break;
    default:
        $response = handle_generic();
        break;
}

if ($response !== null) {
    header('Content-Type: application/json; charset=utf-8');
    echo ")]}'" . json_encode($response);
}