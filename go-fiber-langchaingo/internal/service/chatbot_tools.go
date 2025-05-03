package service

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
	"time"

	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/pkg/logger"
	"github.com/tmc/langchaingo/llms"
)

// ProcessUserQueryWithTools processes a user query using tool calling
func (s *ChatbotService) ProcessUserQueryWithTools(ctx context.Context, userQuery string) (string, error) {
	// Reset tool call sequence for this new query
	logger.ResetToolCallSequence()

	// Add user message to LLM
	s.llmClient.AddUserMessage(userQuery)

	// Create tools for the LLM to use
	tools := s.createCongressTools()

	// Track conversation turns for tool calling
	var finalResponse string
	maxTurns := 5 // Prevent infinite loops

	// Log detailed information about the query
	logger.InfoLogger.Printf("Processing user query with tools: %s", userQuery)
	logger.LogDetailedLLMInteraction("QUERY", map[string]interface{}{
		"query":        userQuery,
		"tools_count":  len(tools),
		"max_turns":    maxTurns,
		"timestamp":    time.Now().Format(time.RFC3339),
	})

	for i := 0; i < maxTurns; i++ {
		logger.InfoLogger.Printf("Tool calling turn %d of %d", i+1, maxTurns)

		// Generate response with tools
		resp, toolCalls, err := s.llmClient.GenerateResponseWithTools(ctx, tools)
		if err != nil {
			logger.ErrorLogger.Printf("Failed to generate response with tools: %v", err)
			return "", fmt.Errorf("failed to generate response with tools: %w", err)
		}

		// If we got a regular response (no tool calls), check if we should force tool usage
		if resp != "" {
			logger.InfoLogger.Printf("Received regular response (no tool calls)")

			// Check if the query is about current information that should use tools
			lowerQuery := strings.ToLower(userQuery)

			// Check for keywords that indicate the query is about current information
			shouldForceToolUsage := (strings.Contains(lowerQuery, "current") ||
				strings.Contains(lowerQuery, "recent") ||
				strings.Contains(lowerQuery, "latest") ||
				strings.Contains(lowerQuery, "now") ||
				strings.Contains(lowerQuery, "today") ||
				strings.Contains(lowerQuery, "119th congress")) &&
				(strings.Contains(lowerQuery, "congress") ||
				 strings.Contains(lowerQuery, "legislation") ||
				 strings.Contains(lowerQuery, "bill") ||
				 strings.Contains(lowerQuery, "senator") ||
				 strings.Contains(lowerQuery, "representative"))

			if shouldForceToolUsage {
				logger.InfoLogger.Printf("Forcing tool usage for query about current information: %s", userQuery)

				// Determine which tool to use based on the query
				var toolName string
				var args map[string]interface{}

				if strings.Contains(lowerQuery, "legislation") || strings.Contains(lowerQuery, "bill") {
					toolName = "search_bills"
					searchQuery := "119th congress recent legislation"
					if strings.Contains(lowerQuery, "specific") {
						searchQuery = "119th congress major legislation"
					}
					args = map[string]interface{}{"query": searchQuery}
				} else if strings.Contains(lowerQuery, "senator") || strings.Contains(lowerQuery, "representative") {
					toolName = "search_members"
					args = map[string]interface{}{"query": "current members 119th congress"}
				} else {
					toolName = "search_bills"
					args = map[string]interface{}{"query": "119th congress recent legislation"}
				}

				// Create a tool call
				toolCallID := fmt.Sprintf("call_%d", len(s.llmClient.GetMessages()))
				argsJSON, _ := json.Marshal(args)

				logger.LogToolCall(toolName, string(argsJSON))
				logger.LogToolSelectionReasoning(
					userQuery,
					toolName,
					"Forced tool usage for query about current information",
				)

				// Execute the tool
				toolResponse, err := s.executeCongressTool(ctx, toolName, string(argsJSON))
				if err != nil {
					logger.ErrorLogger.Printf("Forced tool execution failed: %v", err)
					toolResponse = fmt.Sprintf("Error executing tool: %v", err)
					logger.LogToolCallResponse(toolCallID, "", err)
				} else {
					logger.LogToolCallResponse(toolCallID, toolResponse, nil)
				}

				// Add the tool response to the conversation
				s.llmClient.AddToolResponse(toolCallID, toolResponse)

				// Continue the conversation with the tool response
				continue
			}

			// If we're not forcing tool usage, use the regular response
			finalResponse = resp

			// Log the decision to provide a direct response
			logger.LogToolSelectionReasoning(
				userQuery,
				"direct_response",
				"LLM decided to provide a direct response without using tools",
			)
			break
		}

		// Process each tool call
		if len(toolCalls) > 0 {
			logger.InfoLogger.Printf("Processing %d tool calls", len(toolCalls))

			for _, tc := range toolCalls {
				// Log the tool call with enhanced logging
				logger.LogToolCall(tc.Name, tc.Args)
				logger.LogToolCallSequence(tc.ID, tc.Name, tc.Args)

				// Log the reasoning for selecting this tool
				logger.LogToolSelectionReasoning(
					userQuery,
					tc.Name,
					fmt.Sprintf("Selected tool %s with arguments %s", tc.Name, tc.Args),
				)

				// Execute the appropriate tool based on name
				toolResponse, err := s.executeCongressTool(ctx, tc.Name, tc.Args)
				if err != nil {
					logger.ErrorLogger.Printf("Tool execution failed: %v", err)
					toolResponse = fmt.Sprintf("Error executing tool: %v", err)
					logger.LogToolCallResponse(tc.ID, "", err)
				} else {
					logger.LogToolCallResponse(tc.ID, toolResponse, nil)
				}

				// Add the tool response to the conversation
				s.llmClient.AddToolResponse(tc.ID, toolResponse)
			}

			// Continue the conversation with tool responses
			continue
		}

		// If no tool calls and no response, something went wrong
		if resp == "" && len(toolCalls) == 0 {
			logger.ErrorLogger.Printf("No response and no tool calls received")
			return s.generateDirectResponse(ctx, userQuery)
		}
	}

	// Log a summary of all tool calls made during this query
	logger.LogToolCallSummary()

	// If we didn't get a final response after max turns, fall back to direct response
	if finalResponse == "" {
		logger.LogFallback("Reached maximum conversation turns without final response")
		return s.generateDirectResponseWithWarning(ctx, userQuery)
	}

	return finalResponse, nil
}

// createCongressTools creates tools for the Congress API
func (s *ChatbotService) createCongressTools() []llms.Tool {
	// Create tools that work with langchaingo v0.1.13

	// 1. Bill-Related Tools
	searchBillsTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "search_bills",
			Description: "Search for bills in Congress by keyword. Use this when the user asks about legislation in general or specific bills by keyword.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"query": map[string]any{
						"type":        "string",
						"description": "Search query for bills (e.g., 'infrastructure', 'healthcare', 'education')",
					},
				},
				"required": []string{"query"},
			},
		},
	}

	getBillTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_bill",
			Description: "Get details about a specific bill by congress number and bill number. Use this when the user asks about a specific bill identified by congress number and bill number.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"congress": map[string]any{
						"type":        "string",
						"description": "Congress number (e.g., '119' for current congress, '118' for previous congress)",
					},
					"billNumber": map[string]any{
						"type":        "string",
						"description": "Bill number including type prefix (e.g., 'hr1', 's2043', 'hjres43')",
					},
				},
				"required": []string{"congress", "billNumber"},
			},
		},
	}

	getBillSummaryTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_bill_summary",
			Description: "Get the summary of a specific bill. Use this when the user asks for a summary or explanation of what a specific bill does.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"congress": map[string]any{
						"type":        "string",
						"description": "Congress number (e.g., '119' for current congress, '118' for previous congress)",
					},
					"billNumber": map[string]any{
						"type":        "string",
						"description": "Bill number including type prefix (e.g., 'hr1', 's2043', 'hjres43')",
					},
				},
				"required": []string{"congress", "billNumber"},
			},
		},
	}

	getBillActionsTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_bill_actions",
			Description: "Get the timeline and status updates for a specific bill. Use this when the user asks about the status, progress, or history of a specific bill.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"congress": map[string]any{
						"type":        "string",
						"description": "Congress number (e.g., '119' for current congress, '118' for previous congress)",
					},
					"billNumber": map[string]any{
						"type":        "string",
						"description": "Bill number including type prefix (e.g., 'hr1', 's2043', 'hjres43')",
					},
				},
				"required": []string{"congress", "billNumber"},
			},
		},
	}

	getBillCosponsorsTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_bill_cosponsors",
			Description: "Get the list of cosponsors for a specific bill. Use this when the user asks about who supported or cosponsored a specific bill.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"congress": map[string]any{
						"type":        "string",
						"description": "Congress number (e.g., '119' for current congress, '118' for previous congress)",
					},
					"billNumber": map[string]any{
						"type":        "string",
						"description": "Bill number including type prefix (e.g., 'hr1', 's2043', 'hjres43')",
					},
				},
				"required": []string{"congress", "billNumber"},
			},
		},
	}

	getBillRelatedBillsTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_bill_related_bills",
			Description: "Get bills related to a specific bill. Use this when the user asks about bills similar to a specific bill or related legislation.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"congress": map[string]any{
						"type":        "string",
						"description": "Congress number (e.g., '119' for current congress, '118' for previous congress)",
					},
					"billNumber": map[string]any{
						"type":        "string",
						"description": "Bill number including type prefix (e.g., 'hr1', 's2043', 'hjres43')",
					},
				},
				"required": []string{"congress", "billNumber"},
			},
		},
	}

	// 2. Member-Related Tools
	searchMembersTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "search_members",
			Description: "Search for members of Congress by state, name, or other criteria. Use this when the user asks about members of Congress in general.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"query": map[string]any{
						"type":        "string",
						"description": "Search query for members (e.g., 'Washington state senators', 'Maria Cantwell', 'Texas representatives')",
					},
				},
				"required": []string{"query"},
			},
		},
	}

	getMemberTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_member",
			Description: "Get detailed information for a specific member of Congress by bioguideId. Use this when the user asks about a specific member identified by bioguideId.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"bioguideId": map[string]any{
						"type":        "string",
						"description": "The bioguide ID of the member (e.g., 'C001075' for Maria Cantwell)",
					},
				},
				"required": []string{"bioguideId"},
			},
		},
	}

	getMemberSponsorshipTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_member_sponsorship",
			Description: "Get legislation sponsored by a specific member of Congress. Use this when the user asks about bills a specific member has sponsored.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"bioguideId": map[string]any{
						"type":        "string",
						"description": "The bioguide ID of the member (e.g., 'C001075' for Maria Cantwell)",
					},
				},
				"required": []string{"bioguideId"},
			},
		},
	}

	getMembersByStateTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_members_by_state",
			Description: "Get all members of Congress (both senators and representatives) filtered by state. Use this when the user asks about all members from a specific state.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"stateCode": map[string]any{
						"type":        "string",
						"description": "Two-letter state code (e.g., 'WA' for Washington, 'TX' for Texas)",
					},
				},
				"required": []string{"stateCode"},
			},
		},
	}

	getSenatorsByStateTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_senators_by_state",
			Description: "Get senators (only Senate members) from a specific state. Use this when the user specifically asks about senators from a state.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"stateCode": map[string]any{
						"type":        "string",
						"description": "Two-letter state code (e.g., 'WA' for Washington, 'TX' for Texas)",
					},
				},
				"required": []string{"stateCode"},
			},
		},
	}

	getRepresentativesByStateTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_representatives_by_state",
			Description: "Get representatives (only House members) from a specific state. Use this when the user specifically asks about representatives from a state.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"stateCode": map[string]any{
						"type":        "string",
						"description": "Two-letter state code (e.g., 'WA' for Washington, 'TX' for Texas)",
					},
				},
				"required": []string{"stateCode"},
			},
		},
	}

	// 3. Committee-Related Tools
	searchCommitteesTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "search_committees",
			Description: "Search for congressional committees. Use this when the user asks about congressional committees in general.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"query": map[string]any{
						"type":        "string",
						"description": "Search query for committees (e.g., 'judiciary', 'armed services', 'finance')",
					},
				},
				"required": []string{"query"},
			},
		},
	}

	getCommitteeTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "get_committee",
			Description: "Get detailed information for a specific congressional committee by ID. Use this when the user asks about a specific committee by ID.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"committeeId": map[string]any{
						"type":        "string",
						"description": "The ID of the committee (e.g., 'SSAP' for Senate Committee on Appropriations)",
					},
				},
				"required": []string{"committeeId"},
			},
		},
	}

	// 4. Amendment-Related Tools
	searchAmendmentsTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "search_amendments",
			Description: "Search for amendments in Congress by keyword. Use this when the user asks about amendments in general.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"query": map[string]any{
						"type":        "string",
						"description": "Search query for amendments (e.g., 'infrastructure', 'healthcare')",
					},
				},
				"required": []string{"query"},
			},
		},
	}

	// 5. Congressional Record Tools
	searchCongressionalRecordTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "search_congressional_record",
			Description: "Search the congressional record for debates, proceedings, or speeches. Use this when the user asks about congressional debates, proceedings, or speeches.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"query": map[string]any{
						"type":        "string",
						"description": "Search query for the congressional record (e.g., 'climate change debate', 'infrastructure speech')",
					},
				},
				"required": []string{"query"},
			},
		},
	}

	// 6. Nomination Tools
	searchNominationsTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "search_nominations",
			Description: "Search for presidential nominations requiring Senate confirmation. Use this when the user asks about presidential nominations.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"query": map[string]any{
						"type":        "string",
						"description": "Search query for nominations (e.g., 'Supreme Court', 'Cabinet', 'Federal Reserve')",
					},
				},
				"required": []string{"query"},
			},
		},
	}

	// 7. Hearing Tools
	searchHearingsTool := llms.Tool{
		Type: "function",
		Function: &llms.FunctionDefinition{
			Name:        "search_hearings",
			Description: "Search for congressional hearings. Use this when the user asks about congressional hearings.",
			Parameters: map[string]any{
				"type": "object",
				"properties": map[string]any{
					"query": map[string]any{
						"type":        "string",
						"description": "Search query for hearings (e.g., 'climate change', 'tech regulation', 'healthcare')",
					},
				},
				"required": []string{"query"},
			},
		},
	}

	// Return all tools
	return []llms.Tool{
		// Bill tools
		searchBillsTool,
		getBillTool,
		getBillSummaryTool,
		getBillActionsTool,
		getBillCosponsorsTool,
		getBillRelatedBillsTool,

		// Member tools
		searchMembersTool,
		getMemberTool,
		getMemberSponsorshipTool,
		getMembersByStateTool,
		getSenatorsByStateTool,
		getRepresentativesByStateTool,

		// Committee tools
		searchCommitteesTool,
		getCommitteeTool,

		// Other tools
		searchAmendmentsTool,
		searchCongressionalRecordTool,
		searchNominationsTool,
		searchHearingsTool,
	}
}

// executeCongressTool executes a Congress API tool
func (s *ChatbotService) executeCongressTool(ctx context.Context, toolName, args string) (string, error) {
	logger.InfoLogger.Printf("Executing Congress tool: %s with args: %s", toolName, args)

	var result map[string]interface{}
	var err error

	switch toolName {
	// Bill-related tools
	case "search_bills":
		var params struct {
			Query string `json:"query"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse search_bills args: %w", err)
		}
		result, err = s.congressClient.SearchBills(params.Query, 0, 5)

	case "get_bill":
		var params struct {
			Congress   string `json:"congress"`
			BillNumber string `json:"billNumber"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_bill args: %w", err)
		}
		result, err = s.congressClient.GetBill(params.Congress, params.BillNumber)

	case "get_bill_summary":
		var params struct {
			Congress   string `json:"congress"`
			BillNumber string `json:"billNumber"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_bill_summary args: %w", err)
		}
		result, err = s.congressClient.GetBillSummary(params.Congress, params.BillNumber)

	case "get_bill_actions":
		var params struct {
			Congress   string `json:"congress"`
			BillNumber string `json:"billNumber"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_bill_actions args: %w", err)
		}
		result, err = s.congressClient.GetBillActions(params.Congress, params.BillNumber)

	case "get_bill_cosponsors":
		var params struct {
			Congress   string `json:"congress"`
			BillNumber string `json:"billNumber"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_bill_cosponsors args: %w", err)
		}
		result, err = s.congressClient.GetBillCosponsors(params.Congress, params.BillNumber)

	case "get_bill_related_bills":
		var params struct {
			Congress   string `json:"congress"`
			BillNumber string `json:"billNumber"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_bill_related_bills args: %w", err)
		}
		result, err = s.congressClient.GetBillRelatedBills(params.Congress, params.BillNumber)

	// Member-related tools
	case "search_members":
		var params struct {
			Query string `json:"query"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse search_members args: %w", err)
		}
		result, err = s.congressClient.SearchMembers(params.Query, 0, 5)

	case "get_member":
		var params struct {
			BioguideId string `json:"bioguideId"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_member args: %w", err)
		}
		result, err = s.congressClient.GetMember(params.BioguideId)

	case "get_member_sponsorship":
		var params struct {
			BioguideId string `json:"bioguideId"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_member_sponsorship args: %w", err)
		}
		result, err = s.congressClient.GetMemberSponsorship(params.BioguideId)

	case "get_members_by_state":
		var params struct {
			StateCode string `json:"stateCode"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_members_by_state args: %w", err)
		}
		// Use the member/{stateCode} endpoint
		result, err = s.congressClient.SearchMembers(params.StateCode, 0, 20)

	case "get_senators_by_state":
		var params struct {
			StateCode string `json:"stateCode"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_senators_by_state args: %w", err)
		}
		// Use the new method specifically for senators
		result, err = s.congressClient.GetSenatorsByState(params.StateCode)

	case "get_representatives_by_state":
		var params struct {
			StateCode string `json:"stateCode"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_representatives_by_state args: %w", err)
		}
		// Use the new method specifically for representatives
		result, err = s.congressClient.GetRepresentativesByState(params.StateCode)

	// Committee-related tools
	case "search_committees":
		var params struct {
			Query string `json:"query"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse search_committees args: %w", err)
		}
		result, err = s.congressClient.SearchCommittees(params.Query, 0, 5)

	case "get_committee":
		var params struct {
			CommitteeId string `json:"committeeId"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse get_committee args: %w", err)
		}
		result, err = s.congressClient.GetCommittee(params.CommitteeId)

	// Amendment-related tools
	case "search_amendments":
		var params struct {
			Query string `json:"query"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse search_amendments args: %w", err)
		}
		result, err = s.congressClient.SearchAmendments(params.Query, 0, 5)

	// Congressional Record tools
	case "search_congressional_record":
		var params struct {
			Query string `json:"query"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse search_congressional_record args: %w", err)
		}
		result, err = s.congressClient.SearchCongressionalRecord(params.Query, 0, 5)

	// Nomination tools
	case "search_nominations":
		var params struct {
			Query string `json:"query"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse search_nominations args: %w", err)
		}
		result, err = s.congressClient.SearchNominations(params.Query, 0, 5)

	// Hearing tools
	case "search_hearings":
		var params struct {
			Query string `json:"query"`
		}
		if err := json.Unmarshal([]byte(args), &params); err != nil {
			return "", fmt.Errorf("failed to parse search_hearings args: %w", err)
		}
		result, err = s.congressClient.SearchHearings(params.Query, 0, 5)

	default:
		return "", fmt.Errorf("unknown tool: %s", toolName)
	}

	if err != nil {
		logger.ErrorLogger.Printf("API call failed: %v", err)
		return "", fmt.Errorf("API call failed: %w", err)
	}

	// Convert result to JSON
	resultJSON, err := json.MarshalIndent(result, "", "  ")
	if err != nil {
		logger.ErrorLogger.Printf("Failed to marshal result: %v", err)
		return "", fmt.Errorf("failed to marshal result: %w", err)
	}

	logger.LogAPIResponse(toolName, string(resultJSON))
	return string(resultJSON), nil
}

// generateDirectResponseWithWarning generates a direct response with a warning about outdated information
func (s *ChatbotService) generateDirectResponseWithWarning(ctx context.Context, userQuery string) (string, error) {
	// Create a more helpful prompt that encourages providing substantive information
	clarificationPrompt := fmt.Sprintf(`
The user asked: "%s"

I wasn't able to retrieve specific data from the Congress.gov API for this question.
Please provide a helpful and informative response using your general knowledge about Congress, legislation, or the topic.

GUIDELINES FOR YOUR DIRECT RESPONSE:
1. Answer the user's question as thoroughly as possible with your general knowledge
2. Always be clear about the currency of your information (e.g., "As of my last update...")
3. Focus on factual, verifiable information rather than speculation
4. Include relevant dates and timelines where appropriate
5. If discussing legislation or congressional actions:
   - Mention when it was proposed/passed if known
   - Note the Congress in which it occurred (e.g., "During the 118th Congress...")
   - Include sponsor names and party affiliations when relevant
6. If discussing members of Congress:
   - Note which Congress they serve(d) in
   - Include relevant committee assignments if known
   - Mention party affiliation and state
7. Be conversational and straightforward, not apologetic
8. If the topic requires very recent information that you may not have:
   - Acknowledge the limitation clearly
   - Provide the most recent information you do have
   - Suggest where the user might find more current information
9. Do not fabricate specific details about bills, votes, or members that you're unsure about

IMPORTANT: Begin your response with "⚠️ Note: This information may not be current." to clearly indicate this is not based on real-time API data.

Respond directly to the user without mentioning the API, technical details, or the fact that this is a fallback response.
`, userQuery)

	// Log the fallback
	logger.LogFallback(fmt.Sprintf("Falling back to direct response for query: %s", userQuery))

	// Add the clarification as a user message, not system message
	s.llmClient.AddUserMessage(clarificationPrompt)

	// Generate a direct response
	response, err := s.llmClient.GenerateResponse(ctx)
	if err != nil {
		logger.ErrorLogger.Printf("Failed to generate direct response: %v", err)
		return "", fmt.Errorf("failed to generate direct response: %w", err)
	}

	return response, nil
}
