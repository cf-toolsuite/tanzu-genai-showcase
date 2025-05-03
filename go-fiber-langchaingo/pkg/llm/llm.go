package llm

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"

	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/pkg/logger"
	"github.com/tmc/langchaingo/llms"
	"github.com/tmc/langchaingo/llms/openai"
)

// ChatMessage interface for all message types
type ChatMessage interface {
	GetContent() string
}

// SystemChatMessage represents a system message
type SystemChatMessage struct {
	Content string
}

// GetContent returns the content of the message
func (m SystemChatMessage) GetContent() string {
	return m.Content
}

// HumanChatMessage represents a message from a human
type HumanChatMessage struct {
	Content string
}

// GetContent returns the content of the message
func (m HumanChatMessage) GetContent() string {
	return m.Content
}

// AIChatMessage represents a message from an AI
type AIChatMessage struct {
	Content string
}

// GetContent returns the content of the message
func (m AIChatMessage) GetContent() string {
	return m.Content
}

// ToolCallMessage represents a tool call message
type ToolCallMessage struct {
	ToolCallID string
	Content    string
}

// GetContent returns the content of the message
func (m ToolCallMessage) GetContent() string {
	return m.Content
}

// ToolCall represents a tool call from the LLM
type ToolCall struct {
	ID       string
	Name     string
	Args     string
	Response string
}

// LLMClient is a client for interacting with LLMs through LangChainGo
type LLMClient struct {
	llm       llms.Model
	messages  []ChatMessage
	model     string
	toolCalls []ToolCall
}

// NewLLMClient creates a new LLM client using the provided API key, URL, and model
func NewLLMClient(apiKey, apiURL, modelName string) (*LLMClient, error) {
	// Note: We're using openai interface here, but GenAI might use a different interface
	// depending on what models are available in the GenAI tile.
	// This may need to be adjusted based on the specific LLM provided by the GenAI tile.
	client, err := openai.New(
		openai.WithToken(apiKey),
		openai.WithBaseURL(apiURL),
	)
	if err != nil {
		return nil, fmt.Errorf("failed to create LLM client: %w", err)
	}

	return &LLMClient{
		llm:      client,
		messages: []ChatMessage{},
		model:    modelName,
	}, nil
}

// AddSystemMessage adds a system message to the conversation
func (c *LLMClient) AddSystemMessage(content string) {
	c.messages = append(c.messages, SystemChatMessage{
		Content: content,
	})
}

// AddUserMessage adds a user message to the conversation
func (c *LLMClient) AddUserMessage(content string) {
	c.messages = append(c.messages, HumanChatMessage{
		Content: content,
	})
}

// AddAssistantMessage adds an assistant message to the conversation
func (c *LLMClient) AddAssistantMessage(content string) {
	c.messages = append(c.messages, AIChatMessage{
		Content: content,
	})
}

// GenerateResponse generates a response from the LLM without tools
func (c *LLMClient) GenerateResponse(ctx context.Context) (string, error) {
	// Log the conversation state
	logger.LogConversationState(len(c.messages), false)

	// In langchaingo v0.1.13, we need to create a prompt from our messages
	var prompt string

	for _, msg := range c.messages {
		var rolePrefix string

		switch msg.(type) {
		case SystemChatMessage:
			rolePrefix = "System: "
		case HumanChatMessage:
			rolePrefix = "Human: "
		case AIChatMessage:
			rolePrefix = "AI: "
		case ToolCallMessage:
			rolePrefix = "Tool: "
		}

		prompt += rolePrefix + msg.GetContent() + "\n"
	}

	// Add a final prompt for the AI to respond to
	prompt += "AI: "

	// Log the LLM request
	logger.LogLLMRequest(prompt, []string{})

	// Use the model from the client
	// Use options for a chat model
	opts := []llms.CallOption{
		llms.WithModel(c.model),
		llms.WithTemperature(0.3), // Lower temperature for more consistent, factual responses
		llms.WithMaxTokens(8192),
	}

	// Use the Call method which takes a prompt string
	resp, err := c.llm.Call(ctx, prompt, opts...)
	if err != nil {
		logger.ErrorLogger.Printf("Failed to generate response: %v", err)
		return "", fmt.Errorf("failed to generate response: %w", err)
	}

	// Log the LLM response
	logger.LogLLMResponse(resp)

	c.AddAssistantMessage(resp)
	return resp, nil
}

// GenerateResponseWithTools generates a response from the LLM with tool calling capabilities
// This implementation is compatible with langchaingo v0.1.13
func (c *LLMClient) GenerateResponseWithTools(ctx context.Context, tools []llms.Tool) (string, []ToolCall, error) {
	// Log the conversation state
	logger.LogConversationState(len(c.messages), true)

	// In langchaingo v0.1.13, we need to create a prompt from our messages
	// and include tool definitions in the prompt
	var prompt string

	// Add tool definitions to the system message
	toolsJSON, err := json.MarshalIndent(tools, "", "  ")
	if err != nil {
		logger.ErrorLogger.Printf("Failed to marshal tools: %v", err)
		return "", nil, fmt.Errorf("failed to marshal tools: %w", err)
	}

	// Create a system message with tool definitions
	toolsPrompt := fmt.Sprintf(`
You have access to the following tools:
%s

WHEN TO USE TOOLS:
1. ALWAYS use tools for queries about current information, especially:
   - Current legislation or bills in Congress
   - Current members of Congress
   - Recent congressional activities
   - Specific bill details, summaries, or status updates
   - Committee information
2. Use tools whenever the user asks about specific, factual information that would benefit from up-to-date data
3. Use tools when the user explicitly asks for recent or current information

HOW TO USE TOOLS:
Respond with a JSON object in the following format:
{
  "tool": "tool_name",
  "args": {
    "arg1": "value1",
    "arg2": "value2"
  }
}

IMPORTANT: For queries about current Congress (119th), ALWAYS use a tool rather than providing information from your training data, which may be outdated.

Only respond without using tools if the question is purely conceptual, historical, or about general processes that don't require current data.
`, string(toolsJSON))

	// Add the tools prompt to the beginning of the conversation
	prompt += "System: " + toolsPrompt + "\n"

	// Add the rest of the messages
	for _, msg := range c.messages {
		var rolePrefix string

		switch msg.(type) {
		case SystemChatMessage:
			rolePrefix = "System: "
		case HumanChatMessage:
			rolePrefix = "Human: "
		case AIChatMessage:
			rolePrefix = "AI: "
		case ToolCallMessage:
			rolePrefix = "Tool: "
		}

		prompt += rolePrefix + msg.GetContent() + "\n"
	}

	// Add a final prompt for the AI to respond to
	prompt += "AI: "

	// Log available tools
	toolNames := make([]string, 0, len(tools))
	for _, tool := range tools {
		toolNames = append(toolNames, tool.Function.Name)
	}
	logger.LogLLMRequest(prompt, toolNames)

	// Use the model from the client
	// Use options for a chat model
	opts := []llms.CallOption{
		llms.WithModel(c.model),
		llms.WithTemperature(0.3), // Lower temperature for more consistent, factual responses
		llms.WithMaxTokens(8192),
	}

	// Use the Call method which takes a prompt string
	resp, err := c.llm.Call(ctx, prompt, opts...)
	if err != nil {
		logger.ErrorLogger.Printf("Failed to generate response: %v", err)
		return "", nil, fmt.Errorf("failed to generate response: %w", err)
	}

	// Log the LLM response
	logger.LogLLMResponse(resp)

	// Check if the response is a tool call (JSON object)
	trimmedResp := strings.TrimSpace(resp)
	if len(trimmedResp) > 0 && trimmedResp[0] == '{' {
		// Try to parse as a tool call
		var toolCall struct {
			Tool string                 `json:"tool"`
			Args map[string]interface{} `json:"args"`
		}

		if err := json.Unmarshal([]byte(trimmedResp), &toolCall); err != nil {
			// Not a valid tool call JSON, but let's check if it contains JSON within it
			logger.InfoLogger.Printf("Failed to parse direct tool call: %v", err)

			// Look for JSON objects within the response
			jsonStart := strings.Index(trimmedResp, "{")
			jsonEnd := strings.LastIndex(trimmedResp, "}")

			if jsonStart >= 0 && jsonEnd > jsonStart {
				potentialJSON := trimmedResp[jsonStart : jsonEnd+1]
				if err := json.Unmarshal([]byte(potentialJSON), &toolCall); err == nil && toolCall.Tool != "" {
					// Found a valid tool call JSON within the response
					logger.InfoLogger.Printf("Found embedded tool call JSON: %s", potentialJSON)

					argsJSON, _ := json.Marshal(toolCall.Args)
					logger.LogToolCall(toolCall.Tool, string(argsJSON))

					// Create a unique ID for the tool call
					toolCallID := fmt.Sprintf("call_%d", len(c.toolCalls)+1)

					// Store the tool call
					newToolCall := ToolCall{
						ID:   toolCallID,
						Name: toolCall.Tool,
						Args: string(argsJSON),
					}
					c.toolCalls = append(c.toolCalls, newToolCall)

					// Return the tool call
					return "", []ToolCall{newToolCall}, nil
				}
			}

			// Check for specific keywords that indicate the LLM wants to use a tool
			lowerResp := strings.ToLower(trimmedResp)
			if (strings.Contains(lowerResp, "search_bills") ||
				strings.Contains(lowerResp, "search for bills") ||
				strings.Contains(lowerResp, "search for legislation") ||
				strings.Contains(lowerResp, "i'll search") ||
				strings.Contains(lowerResp, "let me search") ||
				strings.Contains(lowerResp, "i need to search")) &&
				(strings.Contains(lowerResp, "congress") || strings.Contains(lowerResp, "legislation")) {

				// The LLM wants to search for bills but didn't format the tool call correctly
				logger.InfoLogger.Printf("Detected intent to search bills from text: %s", trimmedResp)

				// Extract potential search terms
				var searchQuery string
				if strings.Contains(lowerResp, "119th congress") {
					searchQuery = "119th congress recent legislation"
				} else {
					searchQuery = "recent legislation"
				}

				// Create a tool call for search_bills
				toolCallID := fmt.Sprintf("call_%d", len(c.toolCalls)+1)
				args := map[string]interface{}{"query": searchQuery}
				argsJSON, _ := json.Marshal(args)

				logger.LogToolCall("search_bills", string(argsJSON))

				// Store the tool call
				newToolCall := ToolCall{
					ID:   toolCallID,
					Name: "search_bills",
					Args: string(argsJSON),
				}
				c.toolCalls = append(c.toolCalls, newToolCall)

				// Return the tool call
				return "", []ToolCall{newToolCall}, nil
			}

			// Not a valid tool call, treat as regular response
			c.AddAssistantMessage(resp)
			return resp, nil, nil
		}

		// Valid tool call
		argsJSON, _ := json.Marshal(toolCall.Args)
		logger.LogToolCall(toolCall.Tool, string(argsJSON))

		// Create a unique ID for the tool call
		toolCallID := fmt.Sprintf("call_%d", len(c.toolCalls)+1)

		// Store the tool call
		newToolCall := ToolCall{
			ID:   toolCallID,
			Name: toolCall.Tool,
			Args: string(argsJSON),
		}
		c.toolCalls = append(c.toolCalls, newToolCall)

		// Return the tool call
		return "", []ToolCall{newToolCall}, nil
	}

	// Regular response
	c.AddAssistantMessage(resp)
	return resp, nil, nil
}

// AddToolResponse adds a tool response to the conversation
func (c *LLMClient) AddToolResponse(toolCallID string, content string) {
	// Find the tool call and update its response
	for i, tc := range c.toolCalls {
		if tc.ID == toolCallID {
			c.toolCalls[i].Response = content
			break
		}
	}

	// Add the tool response as a message
	c.messages = append(c.messages, ToolCallMessage{
		ToolCallID: toolCallID,
		Content:    content,
	})

	// Log the tool response
	logger.LogToolCallResult("Tool Response", content, nil)
}

// ClearMessages clears all messages in the conversation
func (c *LLMClient) ClearMessages() {
	c.messages = []ChatMessage{}
}

// GetMessages returns all messages in the conversation
func (c *LLMClient) GetMessages() []ChatMessage {
	return c.messages
}
