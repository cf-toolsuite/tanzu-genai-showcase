package api

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"sync"
	"time"
)

// CongressClient is a client for the Congress.gov API
type CongressClient struct {
	apiKey     string
	baseURL    string
	httpClient *http.Client
	cache      *Cache
}

// Cache provides a simple in-memory caching mechanism
type Cache struct {
	data  map[string]cacheEntry
	mutex sync.RWMutex
}

type cacheEntry struct {
	data       map[string]interface{}
	expiration time.Time
}

// NewCache creates a new cache
func NewCache() *Cache {
	return &Cache{
		data: make(map[string]cacheEntry),
	}
}

// Get retrieves a value from the cache
func (c *Cache) Get(key string) (map[string]interface{}, bool) {
	c.mutex.RLock()
	defer c.mutex.RUnlock()

	entry, found := c.data[key]
	if !found {
		return nil, false
	}

	// Check if the entry has expired
	if time.Now().After(entry.expiration) {
		delete(c.data, key)
		return nil, false
	}

	return entry.data, true
}

// Set stores a value in the cache with an expiration time
func (c *Cache) Set(key string, value map[string]interface{}, expiration time.Duration) {
	c.mutex.Lock()
	defer c.mutex.Unlock()

	c.data[key] = cacheEntry{
		data:       value,
		expiration: time.Now().Add(expiration),
	}
}

// NewCongressClient creates a new Congress.gov API client
func NewCongressClient(apiKey string) *CongressClient {
	return &CongressClient{
		apiKey:     apiKey,
		baseURL:    "https://api.congress.gov/v3",
		httpClient: &http.Client{Timeout: 30 * time.Second},
		cache:      NewCache(),
	}
}

// SearchBills searches for bills in the Congress.gov API
func (c *CongressClient) SearchBills(query string, offset, limit int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/bill", c.baseURL)

	// Build query parameters
	params := url.Values{}
	params.Add("api_key", c.apiKey)
	if query != "" {
		params.Add("query", query)
	}
	params.Add("offset", fmt.Sprintf("%d", offset))
	params.Add("limit", fmt.Sprintf("%d", limit))
	params.Add("sort", "updateDate desc") // Sort by most recent updates

	return c.makeRequest(endpoint, params)
}

// GetBill retrieves a specific bill by congress and bill number
func (c *CongressClient) GetBill(congress, billNumber string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/bill/%s/%s", c.baseURL, congress, billNumber)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// GetBillSummary retrieves the summary of a specific bill
func (c *CongressClient) GetBillSummary(congress, billNumber string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/bill/%s/%s/summaries", c.baseURL, congress, billNumber)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// GetBillActions retrieves the actions taken on a specific bill
func (c *CongressClient) GetBillActions(congress, billNumber string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/bill/%s/%s/actions", c.baseURL, congress, billNumber)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// GetBillCosponsors retrieves the cosponsors of a specific bill
func (c *CongressClient) GetBillCosponsors(congress, billNumber string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/bill/%s/%s/cosponsors", c.baseURL, congress, billNumber)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// GetBillRelatedBills retrieves bills related to a specific bill
func (c *CongressClient) GetBillRelatedBills(congress, billNumber string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/bill/%s/%s/relatedbills", c.baseURL, congress, billNumber)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// SearchMembers searches for members of Congress
func (c *CongressClient) SearchMembers(query string, offset, limit int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/member", c.baseURL)

	params := url.Values{}
	params.Add("api_key", c.apiKey)
	if query != "" {
		params.Add("query", query)
	}
	params.Add("offset", fmt.Sprintf("%d", offset))
	params.Add("limit", fmt.Sprintf("%d", limit))

	return c.makeRequest(endpoint, params)
}

// GetSenatorsByState retrieves senators from a specific state
func (c *CongressClient) GetSenatorsByState(stateCode string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/member", c.baseURL)

	params := url.Values{}
	params.Add("api_key", c.apiKey)
	params.Add("format", "json")

	// Use a more specific query to filter by state and chamber
	query := fmt.Sprintf("state:%s AND chamber:senate", stateCode)
	params.Add("query", query)

	// Limit to 2 results since each state has 2 senators
	params.Add("limit", "2")

	return c.makeRequest(endpoint, params)
}

// GetRepresentativesByState retrieves representatives from a specific state
func (c *CongressClient) GetRepresentativesByState(stateCode string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/member", c.baseURL)

	params := url.Values{}
	params.Add("api_key", c.apiKey)
	params.Add("format", "json")

	// Use a more specific query to filter by state and chamber
	query := fmt.Sprintf("state:%s AND chamber:house", stateCode)
	params.Add("query", query)

	// Set a reasonable limit
	params.Add("limit", "50")

	return c.makeRequest(endpoint, params)
}

// GetMember retrieves a specific member of Congress by bioguideId
func (c *CongressClient) GetMember(bioguideId string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/member/%s", c.baseURL, bioguideId)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// GetMemberSponsorship retrieves sponsorship information for a specific member
func (c *CongressClient) GetMemberSponsorship(bioguideId string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/member/%s/sponsored-legislation", c.baseURL, bioguideId)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// SearchAmendments searches for amendments
func (c *CongressClient) SearchAmendments(query string, offset, limit int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/amendment", c.baseURL)

	params := url.Values{}
	params.Add("api_key", c.apiKey)
	if query != "" {
		params.Add("query", query)
	}
	params.Add("offset", fmt.Sprintf("%d", offset))
	params.Add("limit", fmt.Sprintf("%d", limit))
	params.Add("sort", "updateDate desc") // Sort by most recent updates

	return c.makeRequest(endpoint, params)
}

// SearchCommittees searches for committees
func (c *CongressClient) SearchCommittees(query string, offset, limit int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/committee", c.baseURL)

	params := url.Values{}
	params.Add("api_key", c.apiKey)
	if query != "" {
		params.Add("query", query)
	}
	params.Add("offset", fmt.Sprintf("%d", offset))
	params.Add("limit", fmt.Sprintf("%d", limit))

	return c.makeRequest(endpoint, params)
}

// GetCommittee retrieves a specific committee by ID
func (c *CongressClient) GetCommittee(committeeId string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/committee/%s", c.baseURL, committeeId)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// SearchCongressionalRecord searches the congressional record
func (c *CongressClient) SearchCongressionalRecord(query string, offset, limit int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/congressional-record", c.baseURL)

	params := url.Values{}
	params.Add("api_key", c.apiKey)
	if query != "" {
		params.Add("query", query)
	}
	params.Add("offset", fmt.Sprintf("%d", offset))
	params.Add("limit", fmt.Sprintf("%d", limit))
	params.Add("sort", "date desc") // Sort by most recent first

	return c.makeRequest(endpoint, params)
}

// SearchNominations searches for nominations
func (c *CongressClient) SearchNominations(query string, offset, limit int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/nomination", c.baseURL)

	params := url.Values{}
	params.Add("api_key", c.apiKey)
	if query != "" {
		params.Add("query", query)
	}
	params.Add("offset", fmt.Sprintf("%d", offset))
	params.Add("limit", fmt.Sprintf("%d", limit))
	params.Add("sort", "updateDate desc") // Sort by most recent updates

	return c.makeRequest(endpoint, params)
}

// SearchHearings searches for congressional hearings
func (c *CongressClient) SearchHearings(query string, offset, limit int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/hearing", c.baseURL)

	params := url.Values{}
	params.Add("api_key", c.apiKey)
	if query != "" {
		params.Add("query", query)
	}
	params.Add("offset", fmt.Sprintf("%d", offset))
	params.Add("limit", fmt.Sprintf("%d", limit))
	params.Add("sort", "date desc") // Sort by most recent first

	return c.makeRequest(endpoint, params)
}

// makeRequest makes an HTTP request to the Congress.gov API with caching
func (c *CongressClient) makeRequest(endpoint string, params url.Values) (map[string]interface{}, error) {
	// Create cache key
	cacheKey := fmt.Sprintf("%s?%s", endpoint, params.Encode())

	// Check if we have a cached response
	if cachedResponse, found := c.cache.Get(cacheKey); found {
		return cachedResponse, nil
	}

	// Add parameters to URL
	requestURL := fmt.Sprintf("%s?%s", endpoint, params.Encode())

	// Create request
	req, err := http.NewRequest("GET", requestURL, nil)
	if err != nil {
		return nil, err
	}

	// Set headers
	req.Header.Set("Accept", "application/json")

	// Make request
	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	// Check status code
	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("API request failed with status code: %d", resp.StatusCode)
	}

	// Read response body
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, err
	}

	// Parse JSON
	var result map[string]interface{}
	if err := json.Unmarshal(body, &result); err != nil {
		return nil, err
	}

	// Cache the response for 10 minutes
	c.cache.Set(cacheKey, result, 10*time.Minute)

	return result, nil
}
