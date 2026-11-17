<?php

declare(strict_types=1);

namespace OCA\GroupfolderTags\Controller;

use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\IRequest;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupfolderTags\Errors\TagNotFound;
use OCA\GroupfolderTags\Service\TagService;
use OCA\GroupfolderTags\ResponseDefinitions;

/**
 * @psalm-import-type GroupfolderTagsTag from ResponseDefinitions
 */
class APIController extends OCSController {

    public function __construct(
        string $appName,
        IRequest $request,
        protected readonly FolderManager $folderManager,
        protected readonly TagService $service,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Get all tags optionally filtered by tagKey, tagValue and groupFolderId
     *
     * @param ?string $tagKey filter results to tags with this key
     * @param ?string $tagValue filter results to tags with this value
     * @param ?int $groupFolderId filter results to tags attached to this groupfolder
     * 
     * @return DataResponse<Http::STATUS_OK, array{tags: list<GroupfolderTagsTag>}, array{}>
     *
     * 200: 0 or multiple matching tags returned
     */
    #[ApiRoute(verb: 'GET', url: '/api/v1/tags')]
    public function index(?string $tagKey = null, ?string $tagValue = null, ?int $groupFolderId = null): DataResponse {
        $tags = $this->service->findAll(["tagKey" => $tagKey, "tagValue" => $tagValue, "groupFolderId" => $groupFolderId]);

        return new DataResponse(['tags' => $tags], Http::STATUS_OK);
    }

    /**
     * Get a specific tag by groupfolder id and tag key
     *
     * @param int $groupFolderId The id of the groupfolder to get tag of
     * @param string $tagKey The key of the tag
     * 
     * @return DataResponse<Http::STATUS_OK, array{tag: GroupfolderTagsTag}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
     *
     * 200: 1 tag returned
     * 400: missing or invalid mandatory tagKey parameter
     * 404: tag does not exist
     */
    #[ApiRoute(verb: 'GET', url: '/api/v1/tags/{groupFolderId}/{tagKey}')]
    public function show(int $groupFolderId, string $tagKey): DataResponse {
        if ($tagKey === "") {
            return new DataResponse(['error' => 'No tag key provided'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $tag = $this->service->find($groupFolderId, $tagKey);

            return new DataResponse(['tag' => $tag], Http::STATUS_OK);
        } catch (TagNotFound $e) {
            return new DataResponse(['error' => "Tag {$tagKey} not found!"], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Create a new tag or update an existing tag for the specified groupfolder with the given value
     * Endpoint is idempotent
     *
     * @param int $groupFolderId The id of the groupfolder to create/update the tag of
     * @param string $tagKey The key to set
     * @param ?string $tagValue The optional value to set; omit/set to null to create tag without value or remove value of existing tag
     * 
     * @return DataResponse<Http::STATUS_OK, array{tag: GroupfolderTagsTag}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
     *
     * 200: created/updated tag
     * 400: missing or invalid mandatory tagKey parameter
     * 404: groupfolder with given groupFolderId does not exist
     */
    #[ApiRoute(verb: 'PUT', url: '/api/v1/tags/{groupFolderId}/{tagKey}')]
    public function update(int $groupFolderId, string $tagKey, ?string $tagValue = null): DataResponse {
        if ($tagKey === "") {
            return new DataResponse(['error' => 'No tag key provided!'], Http::STATUS_BAD_REQUEST);
        }

        $folder = $this->folderManager->getFolder($groupFolderId);
		if ($folder === null) {
		    return new DataResponse(['error' => 'Groupfolder does not exist!'], Http::STATUS_NOT_FOUND);
        }

        $tag = $this->service->update($groupFolderId, $tagKey, $tagValue);
        return new DataResponse(['tag' => $tag], Http::STATUS_OK);
    }

    /**
     * Remove the given tag
     *
     * @param int $groupFolderId The id of the groupfolder to remove the tag from
     * @param string $tagKey The key to remove
     * 
     * @return DataResponse<Http::STATUS_OK, array{tag: GroupfolderTagsTag}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
     * 
     * 200: removed tag
     * 400: missing or invalid mandatory tagKey parameter
     * 404: tag does not exist
     */
    #[ApiRoute(verb: 'DELETE', url: '/api/v1/tags/{groupFolderId}/{tagKey}')]
    public function destroy(int $groupFolderId, string $tagKey): DataResponse {
        if ($tagKey === "") {
            return new DataResponse(['error' => 'no tag key provided'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $tag = $this->service->delete($groupFolderId, $tagKey);
            return new DataResponse(['tag' => $tag], Http::STATUS_OK);
        } catch (TagNotFound $e) {
            return new DataResponse(['error' => "Tag {$tagKey} not found!"], Http::STATUS_NOT_FOUND);
        }
    }
}
